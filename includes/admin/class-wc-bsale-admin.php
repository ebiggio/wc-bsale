<?php
/**
 * WC Bsale Admin
 *
 * Entry point for the plugin's admin functionality. It enqueues the admin styles and adds the settings page to the admin menu.
 *
 * @package WC_Bsale
 */

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

/**
 * WC Bsale Admin class
 */
class WC_Bsale_Admin {
	private $settings;

	public function __construct() {
		// Plugin's admin styles
		add_action( 'admin_enqueue_scripts', function () {
			wp_enqueue_style( 'wc-bsale-admin', WC_BSALE_PLUGIN_URL . 'assets/css/wc-bsale.css', array(), WC_BSALE_PLUGIN_VERSION );
		} );

		require_once plugin_dir_path( __FILE__ ) . 'class-wc-bsale-admin-settings.php';

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		$this->settings = new WC_Bsale_Admin_Settings();

		require_once plugin_dir_path( __FILE__ ) . 'class-wc-bsale-admin-hooks.php';
	}

	/**
	 * Add the settings page to the admin menu in the back office of WordPress
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Check if the user has the necessary permissions to access the settings
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_menu_page(
			'WooCommerce Bsale plugin settings', // Page title
			'WooCommerce Bsale', // Menu title
			'manage_options', // Capability
			'wc-bsale-settings', // Menu slug
			array( $this->settings, 'settings_page_content' ), // Callback
			WC_BSALE_PLUGIN_URL . 'assets/images/bsale_icon_bw.png' // Icon
		);
	}
}

new WC_Bsale_Admin();