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
	private object $settings_manager;

	public function __construct() {
		$this->settings_manager = new Settings_Manager();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Add the admin hooks for the stock synchronization
		new Hooks\Stock();

		// Register the AJAX action for searching Bsale offices
		// I do it here because I tried to do it in the Stock_Settings class, and it didn't work (probably because that class is instantiated after the AJAX hooks can be registered).
		add_action( 'wp_ajax_search_bsale_offices', array( 'WC_Bsale\Admin\Settings\Stock_Settings', 'search_bsale_offices' ) );
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
			'WooCommerce Bsale',
			'WooCommerce Bsale',
			'manage_options',
			'wc-bsale-settings',
			array( $this->settings_manager, 'display_settings_page' ),
			PLUGIN_URL . 'assets/images/bsale_icon_bw.png' );

		add_submenu_page(
			'wc-bsale-settings',
			'Settings',
			'Settings',
			'manage_options',
			'wc-bsale-settings',
			array( $this->settings_manager, 'display_settings_page' ) );

		add_submenu_page(
			'wc-bsale-settings',
			'Operations log',
			'Operations log',
			'manage_options',
			'wc-bsale-logs',
			array( new Log_Viewer(), 'log_page_content' ) );
	}
}