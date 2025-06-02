<?php
/**
 * Plugin Name: Grid Aware WordPress
 * Plugin URI: https://github.com/nahuai/grid-aware-wp
 * Description: A plugin that helps manage and optimize grid-based content in WordPress.
 * Version: 1.0.0
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
	$message = __( 'Grid Aware WordPress is active. Some pages will be displayed differently depending on the grid intensity of your visitors.', 'grid-aware-wp' );
	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
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
		wp_die( __( 'Security check failed', 'grid-aware-wp' ) );
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
			'type' => 'object',
			'sanitize_callback' => 'grid_aware_wp_options_sanitize',
			'show_in_rest' => array(
				'schema' => array(
					'type' => 'object',
					'properties' => array(
						'images' => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
						'videos' => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
						'typography' => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
					),
				),
			),
			'default' => array(
				'images' => '1',
				'videos' => '1',
				'typography' => '1',
			),
		)
	);

	// Register page-specific settings meta
	register_post_meta(
		'',
		'grid_aware_wp_page_options',
		array(
			'show_in_rest' => array(
				'schema' => array(
					'type' => 'object',
					'properties' => array(
						'images' => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
						'videos' => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
						'typography' => array(
							'type' => 'string',
							'enum' => array( '0', '1' ),
						),
					),
				),
			),
			'single' => true,
			'type' => 'object',
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
        'images' => '1',
        'videos' => '1',
        'typography' => '1'
    );
    add_option( 'grid_aware_wp_options', $default_options );
}
register_activation_hook( __FILE__, 'grid_aware_wp_activate' );

/**
 * Deactivation hook
 */
function grid_aware_wp_deactivate() {
    // Cleanup if needed
}
register_deactivation_hook( __FILE__, 'grid_aware_wp_deactivate' );

/**
 * Enqueue block editor assets for the preview menu extension (WordPress 6.7+).
 */
function grid_aware_wp_enqueue_editor_assets() {
	$asset_file = include plugin_dir_path( __FILE__ ) . 'build/index.asset.php';
	
	// Get plugin options
	$options = get_option( 'grid_aware_wp_options', array(
		'images'     => '1',
		'videos'     => '1',
		'typography' => '1',
	) );

	// Pass options to JavaScript
	wp_add_inline_script(
		'grid-aware-wp-editor',
		sprintf(
			'window.gridAwareWPOptions = %s;',
			wp_json_encode( $options )
		),
		'before'
	);

	wp_enqueue_script(
		'grid-aware-wp-editor',
		plugins_url( 'build/index.js', __FILE__ ),
		array_merge(
			$asset_file['dependencies'],
			array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data')
		),
		$asset_file['version'],
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'grid_aware_wp_enqueue_editor_assets' );

/**
 * Ensure all options are saved, even if checkboxes are unchecked.
 */
function grid_aware_wp_options_sanitize( $new_value, $old_value = null ) {
	$defaults = array(
		'images'     => '0',
		'videos'     => '0',
		'typography' => '0',
	);
	
	// If $new_value is empty (all unchecked), return all keys as '0'
	if ( empty( $new_value ) || ! is_array( $new_value ) ) {
		return $defaults;
	}
	
	// Merge defaults with new values, so unchecked boxes are saved as '0'
	$new_value = wp_parse_args( $new_value, $defaults );
	
	// Ensure only '0' or '1' are saved
	foreach ( $defaults as $key => $default ) {
		$new_value[ $key ] = ( isset( $new_value[ $key ] ) && '1' === $new_value[ $key ] ) ? '1' : '0';
	}
	
	// Debug log
	error_log( 'Grid Aware WP - Sanitized options: ' . print_r( $new_value, true ) );
	
	return $new_value;
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
	$options = get_option( 'grid_aware_wp_options', array(
		'images'     => '1',
		'videos'     => '1',
		'typography' => '1',
	) );
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

	// Enqueue frontend scripts
	wp_enqueue_script(
		'grid-aware-wp-frontend',
		GRID_AWARE_WP_PLUGIN_URL . 'assets/js/frontend.js',
		array(),
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
}
add_action( 'wp_enqueue_scripts', 'grid_aware_wp_enqueue_frontend_assets' );

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

	// Use page-specific settings only if 'images' key is set, otherwise fallback to global
	if ( ! empty( $page_options ) && isset( $page_options['images'] ) ) {
		$settings = $page_options;
		$settings_source = 'page';
	} else {
		$settings = $global_options;
		$settings_source = 'global';
	}

	// Debug: Log which settings are being used
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Grid Aware WP: Using ' . $settings_source . ' settings: ' . print_r( $settings, true ) );
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
		
		// If there's no alt text, show the placeholder message
		if ( empty( $alt_text ) ) {
			$placeholder = sprintf(
				'<div class="grid-aware-image-placeholder">
					<div class="placeholder-content">
						<p class="placeholder-message">%s</p>
					</div>
				</div>',
				esc_html__( 'This image has not been loaded because the grid intensity is high.', 'grid-aware-wp' )
			);
			return $placeholder;
		}
		
		// If there is alt text, show it with optional caption
		return sprintf(
			'<div class="grid-aware-image-placeholder">
				<div class="placeholder-content">
					<p class="placeholder-alt">%s</p>
					%s
				</div>
			</div>',
			esc_html( $alt_text ),
			! empty( $caption ) ? '<p class="placeholder-caption">' . esc_html( $caption ) . '</p>' : ''
		);
	}

	// For medium intensity, use smaller image size
	if ( 'medium' === $grid_intensity ) {
		// Extract the image ID if available
		preg_match( '/wp-image-(\d+)/', $block_content, $image_id_matches );
		
		if ( ! empty( $image_id_matches[1] ) ) {
			$image_id = $image_id_matches[1];
			$medium_image = wp_get_attachment_image_src( $image_id, 'medium' );
			
			if ( $medium_image ) {
				// Extract original dimensions and srcset
				preg_match( '/width="(\d+)"/', $block_content, $width_matches );
				preg_match( '/height="(\d+)"/', $block_content, $height_matches );
				preg_match( '/srcset="([^"]+)"/', $block_content, $srcset_matches );
				preg_match( '/sizes="([^"]+)"/', $block_content, $sizes_matches );
				
				// Replace the image source with the medium size but keep original dimensions and srcset
				$block_content = preg_replace(
					'/<img[^>]+src="[^"]*"[^>]*>/i',
					sprintf(
						'<img src="%s" width="%s" height="%s" alt="%s" class="wp-image-%d" loading="lazy"%s%s />',
						esc_url( $medium_image[0] ),
						esc_attr( $width_matches[1] ?? 'auto' ),
						esc_attr( $height_matches[1] ?? 'auto' ),
						esc_attr( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ),
						$image_id,
						isset( $srcset_matches[1] ) ? ' srcset="' . esc_attr( $srcset_matches[1] ) . '"' : '',
						isset( $sizes_matches[1] ) ? ' sizes="' . esc_attr( $sizes_matches[1] ) . '"' : ''
					),
					$block_content
				);
			}
		}
	}

	// For low intensity, keep original image but add lazy loading
	if ( 'low' === $grid_intensity ) {
		$block_content = preg_replace(
			'/<img([^>]+)>/i',
			'<img$1 loading="lazy">',
			$block_content
		);
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
    $global_options = get_option( 'grid_aware_wp_options', array(
        'images'     => '1',
        'videos'     => '1',
        'typography' => '1',
    ) );
    
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

	wp_enqueue_script(
		'grid-aware-wp-admin',
		GRID_AWARE_WP_PLUGIN_URL . 'build/index.js',
		array(
			'wp-components',
			'wp-element',
			'wp-api-fetch',
			'wp-i18n',
			'wp-polyfill'
		),
		GRID_AWARE_WP_VERSION,
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


/* attemps to use a pattern to make the grid aware switcher work in the editor, the limitation is that the user can not edit the pattern in the editor 
/**
 * Register block patterns
 
function grid_aware_wp_register_patterns() {
	register_block_pattern(
		'grid-aware-wp/intensity-switcher',
		array(
			'title'       => __( 'Grid Intensity Switcher', 'grid-aware-wp' ),
			'description' => __( 'A switcher to control the grid intensity view.', 'grid-aware-wp' ),
			'categories'  => array( 'grid-aware-wp' ),
			'content'     => '<!-- wp:group {"metadata":{"name":"Grid intensity view switcher"},"align":"full","className":"carbon-switcher","style":{"color":{"background":"#f6f6f6"},"spacing":{"padding":{"right":"var:preset|spacing|40","left":"var:preset|spacing|40","top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group alignfull carbon-switcher has-background" style="background-color:#f6f6f6;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph -->
<p>Grid intensity view</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<select id="carbon-switcher-toggle" class="grid-intensity-select" aria-label="Select grid intensity">
	<option value="live">Live</option>
	<option value="low">Low</option>
	<option value="medium">Medium</option>
	<option value="high">High</option>
</select>
<!-- /wp:html --></div>
<!-- /wp:group -->',
		)
	);
}
add_action( 'init', 'grid_aware_wp_register_patterns' );

/**
 * Register block pattern category
 
function grid_aware_wp_register_pattern_category() {
	register_block_pattern_category(
		'grid-aware-wp',
		array(
			'label' => __( 'Grid Aware WP', 'grid-aware-wp' ),
		)
	);
}
add_action( 'init', 'grid_aware_wp_register_pattern_category' );
/**
 * Add grid intensity switcher to the page
 
function grid_aware_wp_add_intensity_switcher() {
	// Only add on frontend
	if ( is_admin() ) {
		return;
	}

	// Get current intensity from URL
	$current_intensity = isset( $_GET['grid_intensity'] ) ? sanitize_text_field( $_GET['grid_intensity'] ) : 'live';
	
	// Add inline script to set the selected option
	wp_add_inline_script(
		'grid-aware-wp-frontend',
		sprintf(
			'document.addEventListener("DOMContentLoaded", function() {
				var select = document.getElementById("carbon-switcher-toggle");
				if (select) {
					select.value = %s;
				}
			});',
			wp_json_encode( $current_intensity )
		)
	);
	
	// Render the pattern
	echo do_blocks( '<!-- wp:pattern {"slug":"grid-aware-wp/intensity-switcher"} /-->' );
}
add_action( 'wp_body_open', 'grid_aware_wp_add_intensity_switcher' );
*/
