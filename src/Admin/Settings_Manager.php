<?php
/**
 * Settings manager for the plugin.
 *
 * @class   WC_Bsale_Admin_Settings
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin;

use const WC_Bsale\PLUGIN_URL;
use const WC_Bsale\PLUGIN_VERSION;

defined( 'ABSPATH' ) || exit;

/**
 * Settings_Manager class
 */
class Settings_Manager {
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init_settings' ) );

		// Load the admin styles only if we are in a settings page of the plugin
		add_action( 'admin_enqueue_scripts', function ( $hook ) {
			if ( 'toplevel_page_wc-bsale-settings' !== $hook ) {
				return;
			}

			wp_enqueue_style( 'wc-bsale-admin', PLUGIN_URL . 'assets/css/wc-bsale.css', array(), PLUGIN_VERSION );
		} );
	}

	/**
	 * Initializes and registers all the settings of the plugin.
	 *
	 * @return void
	 */
	public function init_settings(): void {
		register_setting( 'wc_bsale_main_settings_group', 'wc_bsale_sandbox_access_token' );
		register_setting( 'wc_bsale_stock_settings_group', 'wc_bsale_admin_stock' );
		register_setting( 'wc_bsale_stock_settings_group', 'wc_bsale_storefront_stock' );
	}

	/**
	 * Displays a settings page according to the selected tab.
	 *
	 * @return void
	 */
	public function settings_page_content(): void {
		// Check if the user has the necessary permissions to access the settings
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Set a success message if the settings were saved
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_message', 'Settings saved', 'updated' );
		}

		global $settings_tabs;
		$settings_tabs = array(
			''      => 'Main settings',
			'stock' => 'Stock synchronization',
			//'prices' => 'Prices synchronization',
			//'orders' => 'Orders events',
		);

		// Include the view that contains the tabs for all the settings
		include plugin_dir_path( __FILE__ ) . 'Settings/Views/Header.php';

		// Include classes according to the selected tab
		$tab = $_GET['tab'] ?? '';

		switch ( $tab ) {
			case 'stock':
				new Settings\Stock_Settings();
				break;
			default:
				new Settings\Main_Settings();
				break;
		}

		// Submit button for the form
		submit_button();
		// Close HTML elements of the view
		echo '</form>';
		echo '</div>';
	}
}