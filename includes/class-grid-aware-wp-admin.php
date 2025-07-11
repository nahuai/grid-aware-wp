<?php
/**
 * Admin functionality for Grid Aware WordPress
 *
 * @package Grid_Aware_WP
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin class for Grid Aware WordPress
 */
class Grid_Aware_WP_Admin {

	/**
	 * Initialize the admin functionality
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_action( 'admin_init', array( $this, 'dismiss_notice' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_footer', array( $this, 'add_rest_nonce' ) );
		add_action( 'admin_init', array( $this, 'activation_redirect' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GRID_AWARE_WP_PLUGIN_DIR . 'grid-aware-wp.php' ), array( $this, 'add_plugin_links' ) );
	}

	/**
	 * Add admin notice for grid-aware functionality
	 */
	public function admin_notice() {
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

	/**
	 * Handle notice dismissal
	 */
	public function dismiss_notice() {
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

	/**
	 * Add admin menu item
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Grid Aware WP', 'grid-aware-wp' ),
			__( 'Grid Aware WP', 'grid-aware-wp' ),
			'manage_options',
			'grid-aware-wp',
			array( $this, 'settings_page' ),
			'dashicons-lightbulb',
			30
		);
	}

	/**
	 * Settings page callback
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Grid Aware WordPress helps you manage and optimize your content layout by providing tools for handling images, videos, and typography in a grid-based system.', 'grid-aware-wp' ); ?></p>
			
			<div id="grid-aware-wp-settings"></div>
		</div>
		<?php
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
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
	}

	/**
	 * Handle activation redirect to settings page
	 */
	public function activation_redirect() {
		if ( get_option( 'grid_aware_wp_do_activation_redirect', false ) ) {
			delete_option( 'grid_aware_wp_do_activation_redirect' );
			if ( ! isset( $_GET['activate-multi'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=grid-aware-wp' ) );
				exit;
			}
		}
	}

	/**
	 * Add settings link to plugin list
	 */
	public function add_plugin_links( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=grid-aware-wp' ) . '">' . __( 'Settings', 'grid-aware-wp' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our settings page
		if ( 'toplevel_page_grid-aware-wp' !== $hook ) {
			return;
		}

		$asset_file = include plugin_dir_path( GRID_AWARE_WP_PLUGIN_DIR . 'grid-aware-wp.php' ) . 'build/index.asset.php';

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

	/**
	 * Add REST API nonce to the page
	 */
	public function add_rest_nonce() {
		wp_nonce_field( 'wp_rest', '_wpnonce', false );
	}
}
