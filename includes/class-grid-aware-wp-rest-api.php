<?php
/**
 * Grid Aware WP REST API Handler
 *
 * @package Grid_Aware_WP
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Grid_Aware_WP_REST_API
 *
 * Handles all REST API functionality for the Grid Aware WordPress plugin.
 */
class Grid_Aware_WP_REST_API {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route(
			'grid-aware-wp/v1',
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args' => array(
					'post_id' => array(
						'required' => false,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'grid-aware-wp/v1',
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args' => array(
					'post_id' => array(
						'required' => false,
						'type'     => 'integer',
					),
				),
			)
		);

		// New endpoint for getting current carbon intensity
		register_rest_route(
			'grid-aware-wp/v1',
			'/intensity',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_current_intensity' ),
				'permission_callback' => '__return_true', // Public endpoint
			)
		);

		// New endpoint for testing API connection
		register_rest_route(
			'grid-aware-wp/v1',
			'/test-api',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_api_connection' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args' => array(
					'api_key' => array(
						'required' => true,
						'type'     => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Get settings via REST API
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function get_settings( $request ) {
		$post_id = $request->get_param( 'post_id' );

		if ( $post_id ) {
			// Get page-specific settings
			$page_options = get_post_meta( $post_id, 'grid_aware_wp_page_options', true );
			if ( ! empty( $page_options ) ) {
				return rest_ensure_response( $page_options );
			}
		}

		// Fallback to global settings
		$options = get_option(
			'grid_aware_wp_options',
			array(
				'images'     => '1',
				'videos'     => '1',
				'typography' => '1',
			)
		);
		return rest_ensure_response( $options );
	}

	/**
	 * Update settings via REST API
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function update_settings( $request ) {
		$params = $request->get_params();
		$options = isset( $params['options'] ) ? $params['options'] : array();
		$post_id = $request->get_param( 'post_id' );

		// Sanitize the options
		$options = $this->sanitize_options( $options, get_option( 'grid_aware_wp_options' ) );

		if ( $post_id ) {
			// Update page-specific settings
			update_post_meta( $post_id, 'grid_aware_wp_page_options', $options );
		} else {
			// Update global settings
			update_option( 'grid_aware_wp_options', $options );
		}

		return rest_ensure_response( $options );
	}

	/**
	 * Get current carbon intensity via REST API
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function get_current_intensity( $request ) {
		// Always use the intensity level endpoint for consistency
		$intensity_data = Grid_Aware_WP_Electricity_Maps_API::get_current_intensity_level();

		if ( is_wp_error( $intensity_data ) ) {
			return new WP_Error(
				'intensity_error',
				$intensity_data->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( $intensity_data );
	}

	/**
	 * Test API connection via REST API
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function test_api_connection( $request ) {
		$api_key = $request->get_param( 'api_key' );

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'API key is required.', 'grid-aware-wp' ),
				array( 'status' => 400 )
			);
		}

		// Test the API connection using the intensity level endpoint
		$test_result = Grid_Aware_WP_Electricity_Maps_API::get_current_intensity_level( $api_key );

		if ( is_wp_error( $test_result ) ) {
			return new WP_Error(
				'api_test_failed',
				$test_result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'API connection successful.', 'grid-aware-wp' ),
				'data'    => $test_result,
			)
		);
	}

	/**
	 * Ensure all options are saved, even if checkboxes are unchecked.
	 *
	 * @param mixed $new_value New value.
	 * @param mixed $old_value Old value.
	 * @return array Sanitized value.
	 */
	private function sanitize_options( $new_value, $old_value = null ) {
		$sanitized_value = array();
		$defaults        = array(
			'images'     => '1',
			'videos'     => '1',
			'typography' => '1',
			'api_key'    => '',
		);

		if ( null === $old_value ) {
			$old_value = get_option( 'grid_aware_wp_options', $defaults );
		}

		$sanitized_value['images']     = isset( $new_value['images'] ) && in_array( $new_value['images'], array( '0', '1' ), true ) ? $new_value['images'] : ( $old_value['images'] ?? $defaults['images'] );
		$sanitized_value['videos']     = isset( $new_value['videos'] ) && in_array( $new_value['videos'], array( '0', '1' ), true ) ? $new_value['videos'] : ( $old_value['videos'] ?? $defaults['videos'] );
		$sanitized_value['typography'] = isset( $new_value['typography'] ) && in_array( $new_value['typography'], array( '0', '1' ), true ) ? $new_value['typography'] : ( $old_value['typography'] ?? $defaults['typography'] );
		$sanitized_value['api_key']    = isset( $new_value['api_key'] ) ? sanitize_text_field( $new_value['api_key'] ) : ( $old_value['api_key'] ?? $defaults['api_key'] );

		return $sanitized_value;
	}
}
