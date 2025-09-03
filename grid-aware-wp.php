<?php
/**
 * Plugin Name: Grid Aware WordPress
 * Plugin URI: https://github.com/nahuai/grid-aware-wp
 * Description: A plugin that helps manage and optimize grid-based content in WordPress.
 * Version: 0.9.1
 * Authors: Nahuai Badiola, Nora Ferreirós
 * Author URI: https://nbadiola.com
 * Plugin URI: https://github.com/nahuai/grid-aware-wp
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
define( 'GRID_AWARE_WP_VERSION', '0.9.1' );
define( 'GRID_AWARE_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRID_AWARE_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Prevent caching when grid_intensity is set (must be before any output)
if ( isset( $_GET['grid_intensity'] ) ) {
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Pragma: no-cache' );
}

// Set the effective grid intensity as early as possible
require_once __DIR__ . '/includes/class-electricity-maps-api.php';
if ( isset( $_GET['grid_intensity'] ) && strtolower( $_GET['grid_intensity'] ) !== 'live' ) {
	$GLOBALS['grid_aware_wp_effective_intensity'] = strtolower( sanitize_text_field( $_GET['grid_intensity'] ) );
} else {
	$data = Grid_Aware_WP_Electricity_Maps_API::get_current_intensity_level();
	if ( ! is_wp_error( $data ) && isset( $data['intensity_level'] ) ) {
		$GLOBALS['grid_aware_wp_effective_intensity'] = strtolower( $data['intensity_level'] );
	} else {
		$GLOBALS['grid_aware_wp_effective_intensity'] = 'low';
	}
}

// Initialize admin functionality
require_once __DIR__ . '/includes/class-grid-aware-wp-admin.php';
new Grid_Aware_WP_Admin();

// Initialize server-side functionality
require_once __DIR__ . '/includes/class-grid-aware-wp-server.php';
new Grid_Aware_WP_Server();

// Initialize REST API functionality
require_once __DIR__ . '/includes/class-grid-aware-wp-rest-api.php';
new Grid_Aware_WP_REST_API();


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
 * Deactivation hook
 */
function grid_aware_wp_deactivate() {
	// Cleanup if needed
	delete_option( 'grid_aware_wp_do_activation_redirect' );
}
register_deactivation_hook( __FILE__, 'grid_aware_wp_deactivate' );

/**
 * Add grid intensity class to body
 */
function grid_aware_wp_add_body_class( $classes ) {
	// Only add on frontend
	if ( is_admin() ) {
		return $classes;
	}

	// Determine the effective grid intensity for this request
	if ( isset( $GLOBALS['grid_aware_wp_effective_intensity'] ) ) {
		$effective_intensity = $GLOBALS['grid_aware_wp_effective_intensity'];
	} else {
		$effective_intensity = 'low';
	}

	// Add the grid intensity class
	$classes[] = 'grid-intensity-' . $effective_intensity;

	return $classes;
}
add_filter( 'body_class', 'grid_aware_wp_add_body_class' );

/**
 * Add intensity info bar and switcher
 */
function grid_aware_wp_add_intensity_info_bar_and_switcher() {
	if ( is_admin() ) {
		return;
	}

	// Fetch dynamic data from EM API
	$data = Grid_Aware_WP_Electricity_Maps_API::get_current_intensity_level();
	if ( ! is_wp_error( $data ) && is_array( $data ) ) {
		$zone = isset( $data['zone'] ) ? strtoupper( $data['zone'] ) : '??';
		$intensity_label = isset( $data['intensity_level'] ) ? strtoupper( $data['intensity_level'] ) : 'UNKNOWN';
	} else {
		$zone = '??';
		$intensity_label = 'UNKNOWN';
	}

	// Determine the effective grid intensity for this request
	if ( isset( $_GET['grid_intensity'] ) && strtolower( $_GET['grid_intensity'] ) !== 'live' ) {
		$GLOBALS['grid_aware_wp_effective_intensity'] = strtolower( sanitize_text_field( $_GET['grid_intensity'] ) );
	} else {
		$GLOBALS['grid_aware_wp_effective_intensity'] = isset( $data['intensity_level'] ) ? strtolower( $data['intensity_level'] ) : 'low';
	}

	// Get current intensity from URL
	$current_intensity = isset( $_GET['grid_intensity'] ) ? strtolower( sanitize_text_field( $_GET['grid_intensity'] ) ) : 'low';
	$intensities = array(
		'low'    => __( 'LOW', 'grid-aware-wp' ),
		'medium' => __( 'MEDIUM', 'grid-aware-wp' ),
		'high'   => __( 'HIGH', 'grid-aware-wp' ),
	);
	?>
	<div class="grid-intensity-info-bar">
		<div class="grid-info-left">
			<span class="grid-info-title">YOUR GRID INFO
				<span class="info-tooltip" tabindex="0" data-tooltip="Indicates how polluting power generation is at your location.">&#8505;</span>
			</span>
			<span class="grid-info-country"><?php echo esc_html( $zone ); ?></span>
			<span class="grid-info-intensity-label"><strong><?php echo esc_html( $intensity_label ); ?> INTENSITY</strong></span>
		</div>
		<div class="grid-info-right">
			<span class="grid-design-title">GRID-AWARE DESIGN
				<span class="info-tooltip" tabindex="0" data-tooltip="The layout adapts based on the grid intensity detected at your location. You can also manually select the consumption mode.">&#8505;</span>
			</span>
			<span class="grid-intensity-toggle-bar">
				<div class="carbon-switcher-wrapper in-bar">
					<div class="carbon-switcher" style="font-family: inherit;">
						<div class="grid-intensity-toggle-group" role="group" aria-label="<?php esc_attr_e( 'Select grid intensity', 'grid-aware-wp' ); ?>">
							<?php
							foreach ( $intensities as $intensity_value_key => $intensity_label_val ) {
								$active_class = ( $intensity_value_key === $current_intensity ) ? 'active' : '';
								?>
								<label class="grid-intensity-toggle <?php echo esc_attr( $active_class ); ?>">
									<input type="checkbox" name="grid_intensity" value="<?php echo esc_attr( $intensity_value_key ); ?>" <?php checked( $current_intensity, $intensity_value_key ); ?> hidden />
									<span><?php echo esc_html( $intensity_label_val ); ?></span>
								</label>
								<?php
							}
							?>
						</div>
					</div>
				</div>
			</span>
		</div>
	</div>
	<script>
	window.gridAwareWPLiveIntensity = '<?php echo esc_js( strtolower( $intensity_label ) ); ?>';
	</script>
	<?php
}
remove_action( 'wp_body_open', 'grid_aware_wp_add_intensity_info_bar', 5 );
remove_action( 'wp_body_open', 'grid_aware_wp_add_intensity_switcher', 5 );
add_action( 'wp_body_open', 'grid_aware_wp_add_intensity_info_bar_and_switcher', 5 );


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
 * Add custom CSS when grid intensity is high
 */
function grid_aware_wp_add_high_intensity_css() {
	// Only add on frontend
	if ( is_admin() ) {
		return;
	}

	// Determine the effective grid intensity for this request
	if ( isset( $GLOBALS['grid_aware_wp_effective_intensity'] ) ) {
		$effective_intensity = $GLOBALS['grid_aware_wp_effective_intensity'];
	} else {
		$effective_intensity = 'live';
	}

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

	$css = '';

	// If images are disabled, override the blur filter for medium intensity
	if ( ! isset( $settings['images'] ) || '0' === $settings['images'] ) {
		$css .= '
		.grid-intensity-medium .wp-block-image img {
			filter: none !important;
		}
		.grid-aware-image-blurred img {
			filter: none !important;
		}
		.grid-intensity-medium .grid-aware-image-blurred img {
			filter: none !important;
		}
		';
	}

	// Add the CSS if we have any
	if ( ! empty( $css ) ) {
		wp_add_inline_style( 'grid-aware-wp-frontend', $css );
	}
}
add_action( 'wp_enqueue_scripts', 'grid_aware_wp_add_high_intensity_css', 20 );

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
	$initial_intensity = isset( $GLOBALS['grid_aware_wp_effective_intensity'] ) ? $GLOBALS['grid_aware_wp_effective_intensity'] : 'low';
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
