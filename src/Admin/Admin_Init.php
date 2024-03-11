<?php
/**
 * Entry point for the plugin's admin functionality.
 *
 * @class   Admin_Init
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin;

use const WC_Bsale\PLUGIN_URL;

defined( 'ABSPATH' ) || exit;

/**
 * Admin_Init class
 */
class Admin_Init {
	private Settings_Manager $settings;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		$this->settings = new Settings_Manager();

		// Add the admin hooks for the stock synchronization
		new Hooks\Stock();
	}

	/**
	 * Adds the settings page to the admin menu in the back office of WordPress.
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
			PLUGIN_URL . 'assets/images/bsale_icon_bw.png' // Icon
		);
	}
}