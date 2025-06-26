<?php
/**
 * Plugin Name: Grid Aware WordPress
 * Plugin URI: https://github.com/nahuai/grid-aware-wp
 * Description: A plugin that helps manage and optimize grid-based content in WordPress.
 * Version: 1.0.1
 * Author: Nahuai
 * Author URI: https://github.com/nahuai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: grid-aware-wp
 * Domain Path: /languages
 *
 * @package Grid_Aware_WP
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'GRID_AWARE_WP_VERSION', '1.0.0' );
define( 'GRID_AWARE_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRID_AWARE_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once GRID_AWARE_WP_PLUGIN_DIR . 'includes/class-electricity-maps-api.php';

/**
 * Add admin notice for grid-aware functionality
 */
function grid_aware_wp_admin_notice() {
	$user_id = get_current_user_id();
	$dismissed = get_user_meta( $user_id, 'grid_aware_wp_notice_dismissed', true );
	$remind_later = get_user_meta( $user_id, 'grid_aware_wp_notice_remind_later', true );
	
	// Check if notice should be shown
	if ( $dismissed || ( $remind_later && time() < $remind_later ) ) {
		return;
	}

	$class = 'notice notice-info is-dismissible';
	$message = sprintf(
		/* translators: %1$s: opening link tag, %2$s: closing link tag */
		__( 'Grid Aware WordPress is active. Some pages will be displayed differently depending on the grid intensity of your visitors. %1$sConfigure settings%2$s', 'grid-aware-wp' ),
		'<a href="' . esc_url( admin_url( 'admin.php?page=grid-aware-wp' ) ) . '">',
		'</a>'
	);
	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $message ) );
}
add_action( 'admin_notices', 'grid_aware_wp_admin_notice' );

/**
 * Handle notice dismissal
 */
function grid_aware_wp_dismiss_notice() {
	if ( ! isset( $_GET['grid_aware_wp_dismiss'] ) || ! isset( $_GET['_wpnonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'grid_aware_wp_dismiss' ) ) {
		wp_die( esc_html__( 'Security check failed', 'grid-aware-wp' ) );
	}

	$user_id = get_current_user_id();
	update_user_meta( $user_id, 'grid_aware_wp_notice_dismissed', '1' );

	wp_safe_redirect( remove_query_arg( array( 'grid_aware_wp_dismiss', '_wpnonce' ) ) );
	exit;
}
add_action( 'admin_init', 'grid_aware_wp_dismiss_notice' );

/**
 * Add admin menu item
 */
function grid_aware_wp_add_admin_menu() {
	add_menu_page(
		__( 'Grid Aware WP', 'grid-aware-wp' ),
		__( 'Grid Aware WP', 'grid-aware-wp' ),
		'manage_options',
		'grid-aware-wp',
		'grid_aware_wp_settings_page',
		'dashicons-lightbulb',
		30
	);
}
add_action( 'admin_menu', 'grid_aware_wp_add_admin_menu' );

/**
 * Register REST API routes
 */
function grid_aware_wp_register_rest_routes() {
	register_rest_route(
		'grid-aware-wp/v1',
		'/settings',
		array(
			'methods'             => 'GET',
			'callback'            => 'grid_aware_wp_get_settings',
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
			'callback'            => 'grid_aware_wp_update_settings',
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
			'callback'            => 'grid_aware_wp_get_current_intensity',
			'permission_callback' => '__return_true', // Public endpoint
			'args' => array(
				'zone' => array(
					'required' => false,
					'type'     => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);

	// New endpoint for testing API connection
	register_rest_route(
		'grid-aware-wp/v1',
		'/test-api',
		array(
			'methods'             => 'POST',
			'callback'            => 'grid_aware_wp_test_api_connection',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args' => array(
				'api_key' => array(
					'required' => true,
					'type'     => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'zone' => array(
					'required' => false,
					'type'     => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'  => 'FR',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'grid_aware_wp_register_rest_routes' );

/**
 * Register plugin settings
 */
function grid_aware_wp_register_settings() {
	// Register global settings
	register_setting(
		'grid_aware_wp_settings',
		'grid_aware_wp_options',
		array(
			'type'              => 'object',
			'sanitize_callback' => 'grid_aware_wp_options_sanitize',
			'show_in_rest'      => array(
				'schema' => array(
					'type'       => 'object',
					'properties' => array(
						'images'     => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
						'videos'     => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
						'typography' => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
						'api_key'    => array(
							'type' => 'string',
						),
					),
				),
			),
			'default'           => array(
				'images'     => '1',
				'videos'     => '1',
				'typography' => '1',
				'api_key'    => '',
			),
		)
	);

	// Register page-specific settings meta
	register_post_meta(
		'',
		'grid_aware_wp_page_options',
		array(
			'show_in_rest'      => array(
				'schema' => array(
					'type'       => 'object',
					'properties' => array(
						'images'     => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
						'videos'     => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
						'typography' => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
						'api_key'    => array(
							'type' => 'string',
						),
					),
				),
			),
			'single'            => true,
			'type'              => 'object',
			'sanitize_callback' => 'grid_aware_wp_options_sanitize',
		)
	);

	add_settings_section(
		'grid_aware_wp_main_section',
		__( 'Grid Settings', 'grid-aware-wp' ),
		'grid_aware_wp_section_callback',
		'grid-aware-wp'
	);

	add_settings_field(
		'grid_aware_wp_images',
		__( 'Images', 'grid-aware-wp' ),
		'grid_aware_wp_images_callback',
		'grid-aware-wp',
		'grid_aware_wp_main_section'
	);

	add_settings_field(
		'grid_aware_wp_videos',
		__( 'Videos', 'grid-aware-wp' ),
		'grid_aware_wp_videos_callback',
		'grid-aware-wp',
		'grid_aware_wp_main_section'
	);

	add_settings_field(
		'grid_aware_wp_typography',
		__( 'Typography', 'grid-aware-wp' ),
		'grid_aware_wp_typography_callback',
		'grid-aware-wp',
		'grid_aware_wp_main_section'
	);
}
add_action( 'admin_init', 'grid_aware_wp_register_settings' );

/**
 * Settings page callback
 */
function grid_aware_wp_settings_page() {
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p><?php esc_html_e( 'Grid Aware WordPress helps you manage and optimize your content layout by providing tools for handling images, videos, and typography in a grid-based system.', 'grid-aware-wp' ); ?></p>
		
		<div id="grid-aware-wp-settings"></div>
	</div>
	<?php
}

/**
 * Section callback
 */
function grid_aware_wp_section_callback() {
	echo '<p>' . esc_html__( 'Select which features you want to enable:', 'grid-aware-wp' ) . '</p>';
}

/**
 * Images field callback
 */
function grid_aware_wp_images_callback() {
	$options = get_option( 'grid_aware_wp_options', array( 'images' => '1' ) );
	?>
	<input type="checkbox" id="grid_aware_wp_images" name="grid_aware_wp_options[images]" value="1" <?php checked( isset( $options['images'] ) ? $options['images'] : '1', '1' ); ?> />
	<label for="grid_aware_wp_images"><?php esc_html_e( 'Enable grid-aware image handling', 'grid-aware-wp' ); ?></label>
	<p class="description"><?php esc_html_e( 'Optimizes images based on grid intensity, adjusting quality and loading strategies to reduce energy consumption.', 'grid-aware-wp' ); ?></p>
	<?php
}

/**
 * Videos field callback
 */
function grid_aware_wp_videos_callback() {
	$options = get_option( 'grid_aware_wp_options', array( 'videos' => '1' ) );
	?>
	<input type="checkbox" id="grid_aware_wp_videos" name="grid_aware_wp_options[videos]" value="1" <?php checked( isset( $options['videos'] ) ? $options['videos'] : '1', '1' ); ?> />
	<label for="grid_aware_wp_videos"><?php esc_html_e( 'Enable grid-aware video handling', 'grid-aware-wp' ); ?></label>
	<p class="description"><?php esc_html_e( 'Manages video playback and quality based on grid conditions, potentially reducing resolution or deferring autoplay during high-intensity periods.', 'grid-aware-wp' ); ?></p>
	<?php
}

/**
 * Typography field callback
 */
function grid_aware_wp_typography_callback() {
	$options = get_option( 'grid_aware_wp_options', array( 'typography' => '1' ) );
	?>
	<input type="checkbox" id="grid_aware_wp_typography" name="grid_aware_wp_options[typography]" value="1" <?php checked( isset( $options['typography'] ) ? $options['typography'] : '1', '1' ); ?> />
	<label for="grid_aware_wp_typography"><?php esc_html_e( 'Enable grid-aware typography handling', 'grid-aware-wp' ); ?></label>
	<p class="description"><?php esc_html_e( 'Adjusts font loading and rendering based on grid intensity, optimizing for energy efficiency while maintaining readability.', 'grid-aware-wp' ); ?></p>
	<?php
}

/**
 * Activation hook
 */
function grid_aware_wp_activate() {
	// Set default options
	$default_options = array(
		'images'     => '1',
		'videos'     => '1',
		'typography' => '1',
	);
	add_option( 'grid_aware_wp_options', $default_options );

	// Add activation redirect flag
	add_option( 'grid_aware_wp_do_activation_redirect', true );
}
register_activation_hook( __FILE__, 'grid_aware_wp_activate' );

/**
 * Handle activation redirect to settings page
 */
function grid_aware_wp_activation_redirect() {
	if ( get_option( 'grid_aware_wp_do_activation_redirect', false ) ) {
		delete_option( 'grid_aware_wp_do_activation_redirect' );
		if ( ! isset( $_GET['activate-multi'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=grid-aware-wp' ) );
			exit;
		}
	}
}
add_action( 'admin_init', 'grid_aware_wp_activation_redirect' );

/**
 * Add settings link to plugin list
 */
function grid_aware_wp_add_plugin_links( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=grid-aware-wp' ) . '">' . __( 'Settings', 'grid-aware-wp' ) . '</a>';
	array_push( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'grid_aware_wp_add_plugin_links' );

/**
 * Deactivation hook
 */
function grid_aware_wp_deactivate() {
	// Cleanup if needed
	delete_option( 'grid_aware_wp_do_activation_redirect' );
}
register_deactivation_hook( __FILE__, 'grid_aware_wp_deactivate' );

/**
 * Enqueue block editor assets for the preview menu extension (WordPress 6.7+).
 */
function grid_aware_wp_enqueue_editor_assets() {
	$asset_file = include plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

	wp_enqueue_script(
		'grid-aware-wp-editor',
		plugins_url( 'build/index.js', __FILE__ ),
		array_merge(
			$asset_file['dependencies'],
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' )
		),
		$asset_file['version'],
		true
	);
	
	// Get plugin options
	$options = get_option( 'grid_aware_wp_options', array() );

	// Pass options to JavaScript
	wp_add_inline_script(
		'grid-aware-wp-editor',
		sprintf(
			'window.gridAwareWPOptions = %s;',
			wp_json_encode( $options )
		),
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'grid_aware_wp_enqueue_editor_assets' );

/**
 * Ensure all options are saved, even if checkboxes are unchecked.
 * @param mixed $new_value New value.
 * @param mixed $old_value Old value.
 * @return array Sanitized value.
 */
function grid_aware_wp_options_sanitize( $new_value, $old_value = null ) {
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

/**
 * Get settings via REST API
 */
function grid_aware_wp_get_settings( $request ) {
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
 */
function grid_aware_wp_update_settings( $request ) {
	$params = $request->get_params();
	$options = isset( $params['options'] ) ? $params['options'] : array();
	$post_id = $request->get_param( 'post_id' );

	// Sanitize the options
	$options = grid_aware_wp_options_sanitize( $options, get_option( 'grid_aware_wp_options' ) );

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
 */
function grid_aware_wp_get_current_intensity( $request ) {
	$zone = $request->get_param( 'zone' );

	// If zone is provided, use it; otherwise get from IP
	if ( ! empty( $zone ) ) {
		$intensity_data = Grid_Aware_WP_Electricity_Maps_API::get_carbon_intensity( $zone );
	} else {
		$intensity_data = Grid_Aware_WP_Electricity_Maps_API::get_current_intensity_level();
	}

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
 */
function grid_aware_wp_test_api_connection( $request ) {
	$api_key = $request->get_param( 'api_key' );
	$zone = $request->get_param( 'zone' );

	if ( empty( $api_key ) ) {
		return new WP_Error(
			'missing_api_key',
			__( 'API key is required.', 'grid-aware-wp' ),
			array( 'status' => 400 )
		);
	}

	// Test the API connection
	$test_result = Grid_Aware_WP_Electricity_Maps_API::get_carbon_intensity( $zone, $api_key );

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
 * Add REST API nonce to the page
 */
function grid_aware_wp_add_rest_nonce() {
	wp_nonce_field( 'wp_rest', '_wpnonce', false );
}
add_action( 'admin_footer', 'grid_aware_wp_add_rest_nonce' );

/**
 * Add grid intensity switcher to the page
 */
function grid_aware_wp_add_intensity_switcher() {
	// Only add on frontend
	if ( is_admin() ) {
		return;
	}

	// Get current intensity from URL
	$current_intensity = isset( $_GET['grid_intensity'] ) ? sanitize_text_field( $_GET['grid_intensity'] ) : 'live';
	?>
	<div class="carbon-switcher-wrapper">
		<div class="carbon-switcher">
			<span>
				<span id="tooltip" class="tooltip" aria-expanded="false" data-tooltip="<?php echo esc_attr( __( 'Grid intensity affects how content is displayed based on current grid conditions.', 'grid-aware-wp' ) ); ?>">?</span>
				<?php echo esc_html( __( 'Grid', 'grid-aware-wp' ) ); ?>
				<span class="hide-on-mobile"> <?php echo esc_html( __( 'intensity', 'grid-aware-wp' ) ); ?> </span>
				<?php echo esc_html( __( 'view:', 'grid-aware-wp' ) ); ?>
			</span>

			<select 
				id="carbon-switcher-toggle" 
				class="grid-intensity-select"
				aria-label="<?php echo esc_attr( __( 'Select grid intensity', 'grid-aware-wp' ) ); ?>"
			>
				<option value="live" <?php selected( $current_intensity, 'live' ); ?>><?php echo esc_html( __( 'Live', 'grid-aware-wp' ) ); ?></option>
				<option value="low" <?php selected( $current_intensity, 'low' ); ?>><?php echo esc_html( __( 'Low', 'grid-aware-wp' ) ); ?></option>
				<option value="medium" <?php selected( $current_intensity, 'medium' ); ?>><?php echo esc_html( __( 'Medium', 'grid-aware-wp' ) ); ?></option>
				<option value="high" <?php selected( $current_intensity, 'high' ); ?>><?php echo esc_html( __( 'High', 'grid-aware-wp' ) ); ?></option>
			</select>
		</div>
	</div>
	<?php
}
add_action( 'wp_body_open', 'grid_aware_wp_add_intensity_switcher' );

/**
 * Register lite-youtube assets for conditional loading
 */
function grid_aware_wp_register_lite_youtube_assets() {
	wp_register_script(
		'lite-youtube',
		plugins_url( 'assets/js/lite-yt-embed.js', __FILE__ ),
		array(),
		'0.2.0',
		true
	);
	wp_register_style(
		'lite-youtube',
		plugins_url( 'assets/css/lite-yt-embed.css', __FILE__ ),
		array(),
		'0.2.0'
	);
}
add_action( 'init', 'grid_aware_wp_register_lite_youtube_assets' );

/**
 * Enqueue lite-youtube assets only when YouTube embeds are present
 */
function grid_aware_wp_enqueue_lite_youtube_assets( $block_content, $block ) {
	// Check if this is a YouTube embed
	if ( 'core/embed' === $block['blockName'] ) {
		$is_youtube = false;
		
		// Check if it's a YouTube embed by looking for YouTube URLs in the content
		if ( preg_match( '/youtube\.com|youtu\.be/', $block_content ) ) {
			$is_youtube = true;
		}
		
		// If it's a YouTube embed, enqueue the lite-youtube assets
		if ( $is_youtube ) {
			wp_enqueue_script( 'lite-youtube' );
			wp_enqueue_style( 'lite-youtube' );
		}
	}
	
	return $block_content;
}
add_filter( 'render_block', 'grid_aware_wp_enqueue_lite_youtube_assets', 10, 2 );

/**
 * Filter image blocks based on grid intensity
 */
function grid_aware_wp_filter_image_block( $block_content, $block ) {
	// Get current page/post ID
	$post_id = get_the_ID();
	
	// Debug: Log the original block content and block data
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Grid Aware WP - Original block content: ' . $block_content );
		error_log( 'Grid Aware WP - Block data: ' . print_r( $block, true ) );
	}
	
	// Get settings - first check page-specific settings, then fallback to global settings
	$page_options = get_post_meta( $post_id, 'grid_aware_wp_page_options', true );
	$global_options = get_option( 'grid_aware_wp_options', array(
		'images'     => '1',
		'videos'     => '1',
		'typography' => '1',
	) );

	// Use page-specific settings if available, otherwise use global settings
	$settings = ! empty( $page_options ) ? $page_options : $global_options;

	// Debug: Log which settings are being used
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Grid Aware WP: Using settings: ' . print_r( $settings, true ) );
	}

	// If images are disabled or not set, return original content immediately
	if ( ! isset( $settings['images'] ) || '0' === $settings['images'] ) {
		// Debug: Log the block content before returning
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Grid Aware WP - Block content before returning (images disabled): ' . $block_content );
			error_log( 'Grid Aware WP - Images are disabled, returning original content' );
		}
		// Return the original block content without any modifications
		return $block_content;
	}

	// Get current grid intensity from URL
	$grid_intensity = isset( $_GET['grid_intensity'] ) ? sanitize_text_field( $_GET['grid_intensity'] ) : 'live';
	
	// Debug: Log the grid intensity and block content before processing
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Grid Aware WP - Current grid intensity: ' . $grid_intensity );
		error_log( 'Grid Aware WP - Block content before processing: ' . $block_content );
	}
	
	// Extract the image ID if available
	$image_id = null;
	if ( preg_match( '/wp-image-(\d+)/', $block_content, $image_id_matches ) ) {
		$image_id = $image_id_matches[1];
	}

	$original_width = '';
	$original_height = '';
	$original_style = '';
	$aspect_ratio = '';

	// If we have an image ID, get the real file dimensions
	if ( $image_id ) {
		$image_path = get_attached_file( $image_id );
		$size = @getimagesize( $image_path );
		if ( $size ) {
			$original_width = $size[0];
			$original_height = $size[1];
			// Use CSS aspect-ratio format: width / height (e.g., 16/9)
			$aspect_ratio = $original_width . ' / ' . $original_height;
		}
	}

	// Prefer the displayed width from the HTML, fallback to 100%
	$displayed_width = '';
	if ( preg_match( '/width="(\d+)"/', $block_content, $width_matches ) ) {
		$displayed_width = $width_matches[1] . 'px';
	} else {
		$displayed_width = '100%';
	}

	// ... fallback to HTML height if needed ...
	if ( ! $original_height && preg_match( '/height="(\d+)"/', $block_content, $height_matches ) ) {
		$original_height = $height_matches[1];
	}
	if ( ! $aspect_ratio && $original_width && $original_height ) {
		$aspect_ratio = $original_width . ' / ' . $original_height;
	}

	// Build style attribute for placeholder using CSS custom properties
	$placeholder_style = '';
	if ( $displayed_width ) {
		$placeholder_style .= '--image-width: ' . $displayed_width . '; ';
	}
	if ( $aspect_ratio ) {
		$placeholder_style .= '--aspect-ratio: ' . $aspect_ratio . '; ';
	}
	if ( $original_style ) {
		$placeholder_style .= $original_style . '; ';
	}

	// If grid intensity is high, don't display the image
	if ( 'high' === $grid_intensity ) {
		// Extract alt text and caption if available
		$alt_text = '';
		$caption = '';

		// Try to get alt text from the image tag
		if ( preg_match( '/<img[^>]+alt="([^"]*)"[^>]*>/i', $block_content, $alt_matches ) ) {
			$alt_text = $alt_matches[1];
		}

		// Try to get caption from figcaption
		if ( preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/i', $block_content, $caption_matches ) ) {
			$caption = $caption_matches[1];
		}

		// Build the placeholder HTML
		$placeholder_html = sprintf(
			'<div class="grid-aware-image-placeholder" data-original-image="%s" onclick="gridAwareWPLoadImage(this)"%s>
				<div class="placeholder-content">
					%s
					%s
				</div>
			</div>',
			esc_attr( $block_content ),
			! empty( $placeholder_style ) ? ' style="' . esc_attr( $placeholder_style ) . '"' : '',
			empty( $alt_text )
				? '<p class="placeholder-message">' . esc_html__( 'This image has not been loaded because the grid intensity is high.', 'grid-aware-wp' ) . '</p>'
				: '<p class="placeholder-alt">' . esc_html( $alt_text ) . '</p>' . ( ! empty( $caption ) ? '<p class="placeholder-caption">' . esc_html( $caption ) . '</p>' : '' ),
			'<p class="placeholder-click-hint">' . esc_html__( 'Click to load image', 'grid-aware-wp' ) . '</p>'
		);

		// Replace only the <img ...> tag with the placeholder
		$block_content = preg_replace(
			'/<img[^>]*>/i',
			$placeholder_html,
			$block_content
		);

		return $block_content;
	}

	// For medium intensity, use smaller image size and 100% width if no width is set
	if ( 'medium' === $grid_intensity ) {
		// Store the original image HTML before modifying it
		$original_image_html = $block_content;
		
		// Extract the image ID if available
		preg_match( '/wp-image-(\d+)/', $block_content, $image_id_matches );
		
		if ( ! empty( $image_id_matches[1] ) ) {
			$image_id = $image_id_matches[1];
			$medium_image = wp_get_attachment_image_src( $image_id, 'medium' );
			
			if ( $medium_image ) {
				// Check if width is explicitly set in the image
				preg_match( '/width="(\d+)"/', $block_content, $width_matches );
				
				// If no width is set, use 100% width
				$image_width = ! empty( $width_matches[1] ) ? $width_matches[1] : '100%';
				
				// Replace the image source with medium size and appropriate width
				$block_content = preg_replace(
					'/<img[^>]+src="[^"]*"[^>]*>/i',
					sprintf(
						'<img src="%s" width="%s" alt="%s" class="wp-image-%d" loading="lazy" />',
						esc_url( $medium_image[0] ),
						esc_attr( $image_width ),
						esc_attr( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ),
						$image_id
					),
					$block_content
				);
			}
		}
		
		// Add click-to-load wrapper for medium intensity images
		$block_content = sprintf(
			'<div class="grid-aware-image-blurred" data-original-image="%s" onclick="gridAwareWPLoadImage(this)">
				%s
				<div class="blur-overlay">
					<div class="blur-message">%s</div>
				</div>
			</div>',
			esc_attr( $original_image_html ),
			$block_content,
			esc_html__( 'Click to load full quality', 'grid-aware-wp' )
		);
	}

	// For low intensity, keep original image but add lazy loading
	if ( 'low' === $grid_intensity ) {
		$block_content = preg_replace(
			'/<img([^>]+)>/i',
			'<img$1 loading="lazy">',
			$block_content
		);
	}

	// For live intensity (default), convert to nocookie domain and add lazy loading
	if ( 'live' === $grid_intensity ) {
		// Convert to nocookie domain first
		$block_content = grid_aware_wp_convert_youtube_to_nocookie( $block_content );
		
		// Add loading="lazy" to iframe if not already present
		if ( ! preg_match( '/loading="lazy"/i', $block_content ) ) {
			$block_content = preg_replace(
				'/<iframe([^>]+)>/i',
				'<iframe$1 loading="lazy">',
				$block_content
			);
		}
	}

	return $block_content;
}
add_filter( 'render_block_core/image', 'grid_aware_wp_filter_image_block', 999, 2 );

/**
 * Filter theme.json data to use system fonts when grid intensity is high
 */
function grid_aware_wp_filter_theme_json_fonts( $theme_json ) {
	// Get current grid intensity from URL
	$grid_intensity = isset( $_GET['grid_intensity'] ) ? sanitize_text_field( $_GET['grid_intensity'] ) : 'live';

	// Get current page/post ID
	$post_id = get_the_ID();

	// Get settings - first check page-specific settings, then fallback to global settings
	$page_options = get_post_meta( $post_id, 'grid_aware_wp_page_options', true );
	$global_options = get_option(
		'grid_aware_wp_options',
		array(
			'images'     => '1',
			'videos'     => '1',
			'typography' => '1',
		)
	);

	// Use page-specific settings if available, otherwise use global settings
	$settings = ! empty( $page_options ) ? $page_options : $global_options;

	// If typography is disabled or not set, return original theme.json
	if ( ! isset( $settings['typography'] ) || '0' === $settings['typography'] ) {
		return $theme_json;
	}

	// If grid intensity is high, replace all font families with system fonts
	if ( 'high' === $grid_intensity ) {
		$new_data = array(
			'version'  => 2,
			'settings' => array(
				'typography' => array(
					'fontFamilies' => array(
						array(
							'fontFamily' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
							'name'       => 'System Font',
							'slug'       => 'system',
						),
					),
				),
			),
		);

		// Update the theme.json data with our system font configuration
		return $theme_json->update_with( $new_data );
	}

	return $theme_json;
}

/**
 * Apply the theme.json filter after theme setup
 */
function grid_aware_wp_apply_theme_json_filters() {
	// Check if the theme has a theme.json file
	if ( wp_theme_has_theme_json() ) {
		add_filter( 'wp_theme_json_data_theme', 'grid_aware_wp_filter_theme_json_fonts' );
	}
}
add_action( 'after_setup_theme', 'grid_aware_wp_apply_theme_json_filters' );

/**
 * Enqueue admin assets
 */
function grid_aware_wp_enqueue_admin_assets( $hook ) {
	// Only load on our settings page
	if ( 'toplevel_page_grid-aware-wp' !== $hook ) {
		return;
	}

	$asset_file = include plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

	wp_enqueue_script(
		'grid-aware-wp-admin',
		GRID_AWARE_WP_PLUGIN_URL . 'build/index.js',
		array_merge( $asset_file['dependencies'], array( 'wp-api-fetch', 'wp-i18n' ) ),
		$asset_file['version'],
		true
	);

	// Add nonce for REST API
	wp_add_inline_script(
		'grid-aware-wp-admin',
		sprintf(
			'window.gridAwareWP = { nonce: "%s" };',
			wp_create_nonce( 'wp_rest' )
		),
		'before'
	);

	// Enqueue WordPress admin styles
	wp_enqueue_style( 'wp-components' );
}
add_action( 'admin_enqueue_scripts', 'grid_aware_wp_enqueue_admin_assets' );

/**
 * Convert YouTube URLs to use the nocookie domain for privacy
 *
 * @param string $content The HTML content containing YouTube embeds.
 * @return string The content with YouTube URLs converted to nocookie domain.
 */
function grid_aware_wp_convert_youtube_to_nocookie( $content ) {
	// Use WP_HTML_Tag_Processor for better HTML parsing
	$processor = new \WP_HTML_Tag_Processor( $content );
	
	while ( $processor->next_tag( 'iframe' ) ) {
		$src = $processor->get_attribute( 'src' );
		if ( $src && ( strpos( $src, 'youtube.com' ) !== false || strpos( $src, 'youtu.be' ) !== false ) ) {
			// Convert to nocookie domain
			$src = str_replace( 'youtube.com', 'youtube-nocookie.com', $src );
			$src = str_replace( 'youtu.be', 'youtube-nocookie.com', $src );
			
			// Add rel=0 parameter to prevent related videos
			if ( strpos( $src, '?feature=oembed' ) !== false ) {
				$src = str_replace( '?feature=oembed', '?feature=oembed&rel=0', $src );
			} elseif ( strpos( $src, '?' ) !== false ) {
				$src .= '&rel=0';
			} else {
				$src .= '?rel=0';
			}
			
			$processor->set_attribute( 'src', $src );
		}
	}
	
	return $processor->get_updated_html();
}

// Helper to output lite-youtube element
function grid_aware_wp_lite_youtube_html( $video_id, $video_title = '' ) {
	if ( empty( $video_title ) ) {
		$video_title = __( 'YouTube video', 'grid-aware-wp' );
	}
	return sprintf(
		'<lite-youtube videoid="%s" style="width:100%%;aspect-ratio:16/9;" title="%s"></lite-youtube>',
		esc_attr( $video_id ),
		esc_attr( $video_title )
	);
}

/**
 * Filter YouTube embed blocks based on grid intensity
 */
function grid_aware_wp_filter_youtube_embed_block( $block_content, $block ) {
	// Get current page/post ID
	$post_id = get_the_ID();

	// Debug: Log the original block content and block data
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Grid Aware WP - YouTube embed original block content: ' . $block_content );
		error_log( 'Grid Aware WP - YouTube embed block data: ' . print_r( $block, true ) );
	}

	// Get settings - first check page-specific settings, then fallback to global settings
	$page_options = get_post_meta( $post_id, 'grid_aware_wp_page_options', true );
	$global_options = get_option( 'grid_aware_wp_options', array(
		'images'     => '1',
		'videos'     => '1',
		'typography' => '1',
	) );

	// Use page-specific settings only if 'videos' key is set, otherwise fallback to global
	if ( ! empty( $page_options ) && isset( $page_options['videos'] ) ) {
		$settings = $page_options;
		$settings_source = 'page';
	} else {
		$settings = $global_options;
		$settings_source = 'global';
	}

	// Debug: Log which settings are being used
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Grid Aware WP: Using ' . $settings_source . ' settings for videos: ' . print_r( $settings, true ) );
	}

	// If videos are disabled or not set, return original content immediately
	if ( ! isset( $settings['videos'] ) || '0' === $settings['videos'] ) {
		// Debug: Log the block content before returning
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Grid Aware WP - YouTube embed block content before returning (videos disabled): ' . $block_content );
			error_log( 'Grid Aware WP - Videos are disabled, returning original content' );
		}
		// Return the original block content without any modifications
		return $block_content;
	}

	// Check if this is a YouTube embed
	$is_youtube = false;
	$video_url = '';
	$video_title = '';
	$video_id = '';

	// Check if it's a YouTube embed by looking for YouTube URLs in the content
	if ( preg_match( '/youtube\.com|youtu\.be/', $block_content ) ) {
		$is_youtube = true;
		
		// Try to extract the video URL
		if ( preg_match( '/src="([^"]*youtube[^"]*)"/i', $block_content, $url_matches ) ) {
			$video_url = $url_matches[1];
		}
		
		// Try to extract title from iframe title attribute
		if ( preg_match( '/title="([^"]*)"/i', $block_content, $title_matches ) ) {
			$video_title = $title_matches[1];
		}
		
		// Extract YouTube video ID from various URL formats
		if ( preg_match( '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $video_url, $embed_matches ) ) {
			$video_id = $embed_matches[1];
		} elseif ( preg_match( '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $video_url, $watch_matches ) ) {
			$video_id = $watch_matches[1];
		} elseif ( preg_match( '/youtu\.be\/([a-zA-Z0-9_-]+)/', $video_url, $short_matches ) ) {
			$video_id = $short_matches[1];
		}
	}

	// If it's not a YouTube embed, return original content
	if ( ! $is_youtube ) {
		return $block_content;
	}

	// Get current grid intensity from URL
	$grid_intensity = isset( $_GET['grid_intensity'] ) ? sanitize_text_field( $_GET['grid_intensity'] ) : 'live';

	// Debug: Log the grid intensity and block content before processing
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Grid Aware WP - Current grid intensity for YouTube: ' . $grid_intensity );
		error_log( 'Grid Aware WP - YouTube block content before processing: ' . $block_content );
	}

	// If grid intensity is high, don't display the video
	if ( 'high' === $grid_intensity ) {
		// Extract dimensions from the iframe
		$video_width = '';
		$video_height = '';
		$video_style = '';
		
		// Extract dimensions from the iframe tag
		if ( preg_match( '/width="(\d+)"/', $block_content, $width_matches ) ) {
			$video_width = $width_matches[1];
		}
		if ( preg_match( '/height="(\d+)"/', $block_content, $height_matches ) ) {
			$video_height = $height_matches[1];
		}
		
		// Extract style attribute if present
		if ( preg_match( '/style="([^"]*)"/', $block_content, $style_matches ) ) {
			$video_style = $style_matches[1];
		}
		
		// Build style attribute for placeholder using CSS custom properties
		$placeholder_style = '';
		if ( $video_width ) {
			$placeholder_style .= '--video-width: ' . $video_width . 'px; ';
		}
		if ( $video_style ) {
			$placeholder_style .= $video_style . '; ';
		}
		
		// Convert the original content to use nocookie domain for when it's loaded
		$original_content_with_nocookie = grid_aware_wp_convert_youtube_to_nocookie( $block_content );
		
		// If there's no title, show the placeholder message
		if ( empty( $video_title ) ) {
			$placeholder = sprintf(
				'<div class="grid-aware-video-placeholder" data-original-video="%s" onclick="gridAwareWPLoadVideo(this)"%s>
					<div class="placeholder-content">
						<p class="placeholder-message">%s</p>
						<p class="placeholder-click-hint">%s</p>
					</div>
				</div>',
				esc_attr( $original_content_with_nocookie ),
				! empty( $placeholder_style ) ? ' style="' . esc_attr( $placeholder_style ) . '"' : '',
				esc_html__( 'This video has not been loaded because the grid intensity is high.', 'grid-aware-wp' ),
				esc_html__( 'Click to load video', 'grid-aware-wp' )
			);
			return $placeholder;
		}

		// If there is a title, show it with a placeholder message
		return sprintf(
			'<div class="grid-aware-video-placeholder" data-original-video="%s" onclick="gridAwareWPLoadVideo(this)"%s>
				<div class="placeholder-content">
					<p class="placeholder-title">%s</p>
					<p class="placeholder-message">%s</p>
					<p class="placeholder-click-hint">%s</p>
				</div>
			</div>',
			esc_attr( $original_content_with_nocookie ),
			! empty( $placeholder_style ) ? ' style="' . esc_attr( $placeholder_style ) . '"' : '',
			esc_html( $video_title ),
			esc_html__( 'This video has not been loaded because the grid intensity is high.', 'grid-aware-wp' ),
			esc_html__( 'Click to load video', 'grid-aware-wp' )
		);
	}

	// For medium intensity, show YouTube thumbnail instead of iframe
	if ( 'medium' === $grid_intensity ) {
		// If we have a video ID, show the thumbnail
		if ( ! empty( $video_id ) ) {
			// Extract dimensions from the iframe
			$video_width = '';
			$video_height = '';
			$video_style = '';
			
			// Extract dimensions from the iframe tag
			if ( preg_match( '/width="(\d+)"/', $block_content, $width_matches ) ) {
				$video_width = $width_matches[1];
			}
			if ( preg_match( '/height="(\d+)"/', $block_content, $height_matches ) ) {
				$video_height = $height_matches[1];
			}
			
			// Extract style attribute if present
			if ( preg_match( '/style="([^"]*)"/', $block_content, $style_matches ) ) {
				$video_style = $style_matches[1];
			}
			
			// Build style attribute for thumbnail using CSS custom properties
			$thumbnail_style = '';
			// Use 100% width for responsive behavior instead of fixed pixel width
			$thumbnail_style .= '--video-width: 100%; ';
			if ( $video_style ) {
				$thumbnail_style .= $video_style . '; ';
			}
			
			// YouTube thumbnail URL (maxresdefault.jpg for highest quality, fallback to hqdefault.jpg)
			$thumbnail_url = 'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg';
			
			// Convert the original content to use nocookie domain for when it's loaded
			$original_content_with_nocookie = grid_aware_wp_convert_youtube_to_nocookie( $block_content );
			
			// If there's no title, show the thumbnail with a message
			if ( empty( $video_title ) ) {
				$thumbnail = sprintf(
					'<div class="grid-aware-video-thumbnail" data-original-video="%s" onclick="gridAwareWPLoadVideo(this)"%s>
						<img src="%s" alt="%s" loading="lazy" />
						<div class="thumbnail-overlay">
							<div class="play-button">▶</div>
							<p class="thumbnail-message">%s</p>
						</div>
					</div>',
					esc_attr( $original_content_with_nocookie ),
					! empty( $thumbnail_style ) ? ' style="' . esc_attr( $thumbnail_style ) . '"' : '',
					esc_url( $thumbnail_url ),
					esc_attr__( 'YouTube video thumbnail', 'grid-aware-wp' ),
					esc_html__( 'Click to load video', 'grid-aware-wp' )
				);
				return $thumbnail;
			}

			// If there is a title, show it with the thumbnail
			return sprintf(
				'<div class="grid-aware-video-thumbnail" data-original-video="%s" onclick="gridAwareWPLoadVideo(this)"%s>
					<img src="%s" alt="%s" loading="lazy" />
					<div class="thumbnail-overlay">
						<div class="play-button">▶</div>
						<p class="thumbnail-title">%s</p>
						<p class="thumbnail-message">%s</p>
					</div>
				</div>',
				esc_attr( $original_content_with_nocookie ),
				! empty( $thumbnail_style ) ? ' style="' . esc_attr( $thumbnail_style ) . '"' : '',
				esc_url( $thumbnail_url ),
				esc_attr( $video_title ),
				esc_html( $video_title ),
				esc_html__( 'Click to load video', 'grid-aware-wp' )
			);
		}
		
		// Fallback: if no video ID found, add lazy loading to iframe and convert to nocookie
		$block_content = grid_aware_wp_convert_youtube_to_nocookie( $block_content );
		if ( ! preg_match( '/loading="lazy"/i', $block_content ) ) {
			$block_content = preg_replace(
				'/<iframe([^>]+)>/i',
				'<iframe$1 loading="lazy">',
				$block_content
			);
		}
	}

	// For low intensity, keep original video but add lazy loading and convert to nocookie
	if ( 'low' === $grid_intensity ) {
		// Convert to nocookie domain first
		$block_content = grid_aware_wp_convert_youtube_to_nocookie( $block_content );
		
		// Add loading="lazy" to iframe if not already present
		if ( ! preg_match( '/loading="lazy"/i', $block_content ) ) {
			$block_content = preg_replace(
				'/<iframe([^>]+)>/i',
				'<iframe$1 loading="lazy">',
				$block_content
			);
		}
	}

	// For live intensity (default), convert to nocookie domain and add lazy loading
	if ( 'live' === $grid_intensity ) {
		// Convert to nocookie domain first
		$block_content = grid_aware_wp_convert_youtube_to_nocookie( $block_content );
		
		// Add loading="lazy" to iframe if not already present
		if ( ! preg_match( '/loading="lazy"/i', $block_content ) ) {
			$block_content = preg_replace(
				'/<iframe([^>]+)>/i',
				'<iframe$1 loading="lazy">',
				$block_content
			);
		}
	}

	if ( $video_id ) {
		return grid_aware_wp_lite_youtube_html( $video_id, $video_title );
	}

	return $block_content;
}
add_filter( 'render_block_core/embed', 'grid_aware_wp_filter_youtube_embed_block', 999, 2 );

/**
 * Enqueue frontend assets
 */
function grid_aware_wp_enqueue_frontend_assets() {
	// Only enqueue on frontend
	if ( is_admin() ) {
		return;
	}

	// Get current page/post ID
	$post_id = get_the_ID();
	
	// Get settings - first check page-specific settings, then fallback to global settings
	$page_options = get_post_meta( $post_id, 'grid_aware_wp_page_options', true );
	$global_options = get_option( 'grid_aware_wp_options', array(
		'images'     => '1',
		'videos'     => '1',
		'typography' => '1',
	) );
	
	// Use page-specific settings if available, otherwise use global settings
	$settings = ! empty( $page_options ) ? $page_options : $global_options;

	// Enqueue frontend styles
	wp_enqueue_style(
		'grid-aware-wp-frontend',
		GRID_AWARE_WP_PLUGIN_URL . 'assets/css/frontend.css',
		array(),
		GRID_AWARE_WP_VERSION
	);

	// Enqueue frontend scripts (no wp-api dependency)
	wp_enqueue_script(
		'grid-aware-wp-frontend',
		GRID_AWARE_WP_PLUGIN_URL . 'assets/js/frontend.js',
		array(), // No wp-api!
		GRID_AWARE_WP_VERSION,
		true
	);

	// Add inline script with settings and initial intensity
	$initial_intensity = isset( $_GET['grid_intensity'] ) ? sanitize_text_field( $_GET['grid_intensity'] ) : 'live';
	wp_add_inline_script(
		'grid-aware-wp-frontend',
		sprintf(
			'window.gridAwareWPSettings = %s; window.gridAwareWPInitialIntensity = %s;',
			wp_json_encode( $settings ),
			wp_json_encode( $initial_intensity )
		),
		'before'
	);

	// Only load click-to-load JavaScript if videos are enabled and grid intensity is high or medium
	if ( isset( $settings['videos'] ) && '1' === $settings['videos'] && ( 'high' === $initial_intensity || 'medium' === $initial_intensity ) ) {
		// Check if the current post has YouTube embeds
		$post_content = get_post_field( 'post_content', $post_id );
		if ( $post_content && ( strpos( $post_content, 'youtube.com' ) !== false || strpos( $post_content, 'youtu.be' ) !== false ) ) {
			wp_add_inline_script(
				'grid-aware-wp-frontend',
				'
				window.gridAwareWPLoadVideo = function(element) {
					var originalVideo = element.getAttribute("data-original-video");
					if (originalVideo) {
						element.innerHTML = originalVideo;
						element.classList.remove("grid-aware-video-placeholder", "grid-aware-video-thumbnail");
						element.classList.add("grid-aware-video-loaded");
					}
				};
				',
				'before'
			);
		}
	}

	// Only load click-to-load JavaScript if images are enabled and grid intensity is high or medium
	if ( isset( $settings['images'] ) && '1' === $settings['images'] && ( 'high' === $initial_intensity || 'medium' === $initial_intensity ) ) {
		// Check if the current post has image blocks
		$post_content = get_post_field( 'post_content', $post_id );
		if ( $post_content && strpos( $post_content, '<!-- wp:image' ) !== false ) {
			wp_add_inline_script(
				'grid-aware-wp-frontend',
				'
				window.gridAwareWPLoadImage = function(element) {
					var originalImage = element.getAttribute("data-original-image");
					if (originalImage) {
						element.innerHTML = originalImage;
						element.classList.remove("grid-aware-image-placeholder", "grid-aware-image-blurred");
						element.classList.add("grid-aware-image-loaded");
						
						// Remove blur filter from the loaded image
						var img = element.querySelector("img");
						if (img) {
							img.style.filter = "none";
						}
					}
				};
				',
				'before'
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'grid_aware_wp_enqueue_frontend_assets' );
