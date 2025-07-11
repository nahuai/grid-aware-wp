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
	 * Get carbon intensity for a specific zone
	 *
	 * @param string $zone_code The zone code (e.g., 'FR', 'DE', 'US-CA')
	 * @param string $api_key The API key
	 * @return array|WP_Error Response data or error
	 */
	public static function get_carbon_intensity( $zone_code, $api_key = '' ) {
		// Get API key from settings if not provided
		if ( empty( $api_key ) ) {
			$options = get_option( 'grid_aware_wp_options', array() );
			$api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
		}

		// Trim whitespace from API key
		$api_key = trim( $api_key );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Electricity Maps API key is required.', 'grid-aware-wp' ) );
		}

		// Check cache first
		$cache_key = 'grid_aware_wp_carbon_intensity_' . sanitize_key( $zone_code );
		$cached_data = get_transient( $cache_key );
		
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Make API request
		$url = self::API_BASE_URL . '/carbon-intensity/latest';
		$args = array(
			'headers' => array(
				'auth-token' => $api_key,
			),
			'timeout' => 10,
		);

		// Add zone parameter
		$url = add_query_arg( 'zone', $zone_code, $url );

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$api_message = '';

			if ( ! empty( $error_data['message'] ) ) {
				$api_message = $error_data['message'];
			} elseif ( ! empty( $error_data['error'] ) ) {
				$api_message = $error_data['error'];
			} else {
				$api_message = wp_remote_retrieve_response_message( $response );
			}

			$full_error_message = sprintf(
				// translators: %s: A detailed error message from the API provider.
				__( 'Electricity Maps API error: %s', 'grid-aware-wp' ),
				$api_message
			);

			return new WP_Error(
				'api_error',
				$full_error_message,
				array(
					'status' => $response_code,
					'body'   => $response_body,
				)
			);
		}

		$data = json_decode( $response_body, true );

		if ( null === $data ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from Electricity Maps API.', 'grid-aware-wp' ) );
		}

		// Cache the response
		set_transient( $cache_key, $data, self::CACHE_DURATION );

		return $data;
	}

	/**
	 * Get carbon intensity level based on carbon intensity value
	 *
	 * @param float $carbon_intensity Carbon intensity in gCO2eq/kWh
	 * @return string Intensity level: 'low', 'medium', or 'high'
	 */
	public static function get_intensity_level( $carbon_intensity ) {
		// These thresholds are based on typical grid carbon intensities
		// Low: < 200 gCO2eq/kWh (renewable-heavy grids)
		// Medium: 200-500 gCO2eq/kWh (mixed grids)
		// High: > 500 gCO2eq/kWh (fossil-heavy grids)
		
		if ( $carbon_intensity < 200 ) {
			return 'low';
		} elseif ( $carbon_intensity < 500 ) {
			return 'medium';
		} else {
			return 'high';
		}
	}

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
	 * Get current carbon intensity level for the visitor.
	 *
	 * This function handles the logic for detecting the visitor's location and
	 * fetching the appropriate carbon intensity data. It uses the recommended
	 * X-Forwarded-For header for direct geolocation by the Electricity Maps API.
	 *
	 * @param string $api_key Optional API key override.
	 * @return array|WP_Error Response with intensity level and data.
	 */
	public static function get_current_intensity_level( $api_key = '' ) {
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

		$url       = self::API_BASE_URL . '/carbon-intensity/latest';
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
			$cache_key = 'grid_aware_wp_ci_es'; // Static cache key for fallback.
		} else {
			// For public visitors, use IP geolocation via header.
			$args['headers']['X-Forwarded-For'] = $visitor_ip;
			// Use a hash of the IP for privacy in the cache key.
			$cache_key = 'grid_aware_wp_ci_' . md5( $visitor_ip );
		}

		// Check cache first.
		$cached_data = get_transient( $cache_key );
		if ( false !== $cached_data ) {
			$intensity_data = $cached_data;
		} else {
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

			// Cache the response.
			set_transient( $cache_key, $intensity_data, self::CACHE_DURATION );
		}

		if ( is_wp_error( $intensity_data ) ) {
			return $intensity_data;
		}

		$carbon_intensity = isset( $intensity_data['carbonIntensity'] ) ? $intensity_data['carbonIntensity'] : null;

		if ( null === $carbon_intensity ) {
			return new WP_Error( 'no_intensity_data', __( 'No carbon intensity data available in the API response.', 'grid-aware-wp' ) );
		}

		// Determine and add our custom intensity level.
		$intensity_data['intensity_level'] = self::get_intensity_level( $carbon_intensity );

		// Ensure zone is set for local fallback.
		if ( $is_local && ! isset( $intensity_data['zone'] ) ) {
			$intensity_data['zone'] = 'ES';
		}

		return $intensity_data;
	}
}
