<?php
/**
 * Electricity Maps API Integration
 *
 * @package Grid_Aware_WP
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Grid_Aware_WP_Electricity_Maps_API
 */
class Grid_Aware_WP_Electricity_Maps_API {

	/**
	 * API base URL
	 */
	const API_BASE_URL = 'https://api.electricitymap.org/v3';

	/**
	 * Cache duration in seconds (10 minutes)
	 */
	const CACHE_DURATION = 600;



	/**
	 * Get visitor's IP address
	 *
	 * @return string IP address
	 */
	private static function get_visitor_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
	}

	/**
	 * Check if an IP address is in a private or reserved range.
	 *
	 * @param string $ip The IP address to check.
	 * @return bool True if the IP is local, false otherwise.
	 */
	private static function is_local_ip( $ip ) {
		// Check for common local IPs first.
		if ( in_array( $ip, array( '127.0.0.1', '::1' ), true ) ) {
			return true;
		}

		// Use FILTER_VALIDATE_IP with flags to check for private and reserved ranges.
		return ! filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	/**
	 * Get current carbon intensity level for the visitor using the carbon-intensity-level endpoint.
	 *
	 * This function uses the /carbon-intensity-level/latest endpoint which directly returns
	 * the categorized intensity level without numerical CO2 values.
	 *
	 * @param string $api_key Optional API key override.
	 * @return array|WP_Error Response with intensity level and data.
	 */
	public static function get_current_intensity_level_direct( $api_key = '' ) {
		// Get API key from settings if not provided.
		if ( empty( $api_key ) ) {
			$options = get_option( 'grid_aware_wp_options', array() );
			$api_key = isset( $options['api_key'] ) ? trim( $options['api_key'] ) : '';
		}

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Electricity Maps API key is required.', 'grid-aware-wp' ) );
		}

		$visitor_ip = self::get_visitor_ip();
		$is_local   = self::is_local_ip( $visitor_ip );

		$url       = self::API_BASE_URL . '/carbon-intensity-level/latest';
		$cache_key = '';
		$args      = array(
			'headers' => array(
				'auth-token' => $api_key,
			),
			'timeout' => 10,
		);

		if ( $is_local ) {
			// For local development, use a fallback zone.
			$url       = add_query_arg( 'zone', 'ES', $url );
			$cache_key = 'grid_aware_wp_cil_es'; // Static cache key for fallback.
		} else {
			// For public visitors, use IP geolocation via header.
			$args['headers']['X-Forwarded-For'] = $visitor_ip;
			// Use a hash of the IP for privacy in the cache key.
			$cache_key = 'grid_aware_wp_cil_' . md5( $visitor_ip );
		}

		// Check cache first.
		$cached_data = get_transient( $cache_key );
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Make the API call.
		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data         = json_decode( $response_body, true );
			$api_message        = '';
			$full_error_message = sprintf(
				__( 'Electricity Maps API error: %s', 'grid-aware-wp' ),
				wp_remote_retrieve_response_message( $response )
			);

			if ( ! empty( $error_data['message'] ) ) {
				$api_message = $error_data['message'];
			} elseif ( ! empty( $error_data['error'] ) ) {
				$api_message = $error_data['error'];
			}

			if ( ! empty( $api_message ) ) {
				$full_error_message = sprintf(
					__( '%1$s error: %2$s', 'grid-aware-wp' ),
					'Electricity Maps',
					$api_message
				);
			}

			return new WP_Error(
				'api_error',
				$full_error_message,
				array(
					'status' => $response_code,
					'body'   => $response_body,
				)
			);
		}

		$intensity_data = json_decode( $response_body, true );

		if ( null === $intensity_data ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from Electricity Maps API.', 'grid-aware-wp' ) );
		}

		// The API returns data in this format according to the documentation:
		// {
		//   "zone": "DE",
		//   "data": [
		//     {
		//       "level": "high",
		//       "datetime": "2025-06-23T11:00:00.000Z"
		//     }
		//   ]
		// }
		
		// Extract the level from the response
		if ( isset( $intensity_data['data'] ) && is_array( $intensity_data['data'] ) && ! empty( $intensity_data['data'] ) ) {
			$latest_data = $intensity_data['data'][0];
			$intensity_level = isset( $latest_data['level'] ) ? $latest_data['level'] : null;
			
			if ( $intensity_level ) {
				// Map Electricity Maps levels to our plugin levels
				// EM uses: "high", "moderate", "low"
				// Plugin uses: "high", "medium", "low"
				$mapped_level = $intensity_level;
				if ( $intensity_level === 'moderate' ) {
					$mapped_level = 'medium';
				}
				
				// Create a normalized response structure
				$normalized_response = array(
					'zone' => isset( $intensity_data['zone'] ) ? $intensity_data['zone'] : 'unknown',
					'intensity_level' => $mapped_level,
					'datetime' => isset( $latest_data['datetime'] ) ? $latest_data['datetime'] : null,
					'raw_data' => $intensity_data // Keep original data for debugging
				);
				
				// Ensure zone is set for local fallback.
				if ( $is_local && ! isset( $normalized_response['zone'] ) ) {
					$normalized_response['zone'] = 'ES';
				}
				
				// Cache the normalized response.
				set_transient( $cache_key, $normalized_response, self::CACHE_DURATION );
				
				return $normalized_response;
			}
		}

		return new WP_Error( 'no_level_data', __( 'No carbon intensity level data available in the API response.', 'grid-aware-wp' ) );
	}

	/**
	 * Get current carbon intensity level for the visitor.
	 *
	 * This function now uses the carbon-intensity-level endpoint directly to get
	 * categorized intensity levels without numerical CO2 values.
	 *
	 * @param string $api_key Optional API key override.
	 * @return array|WP_Error Response with intensity level and data.
	 */
	public static function get_current_intensity_level( $api_key = '' ) {
		// Use the new direct method
		return self::get_current_intensity_level_direct( $api_key );
	}
}
