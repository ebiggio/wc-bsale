<?php
/**
 * Class WC_Bsale_Admin_Settings
 *
 * This class is responsible for the settings of the plugin
 *
 * @package WC_Bsale
 */

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

/**
 * WC Bsale Admin Settings class
 */
class WC_Bsale_Admin_Settings {
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init_settings' ) );
	}

	/**
	 * Initialize and register all the settings of the plugin
	 *
	 * @return void
	 */
	public function init_settings(): void {
		register_setting( 'wc_bsale_main_settings_group', 'wc_bsale_sandbox_access_token' );
		register_setting( 'wc_bsale_stock_settings_group', 'wc_bsale_admin_stock' );
		register_setting( 'wc_bsale_stock_settings_group', 'wc_bsale_storefront_stock' );
	}

	/**
	 * Manages and displays the settings page according to the selected tab
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
		include plugin_dir_path( __FILE__ ) . 'settings/views/html-admin-settings.php';

		// Include classes according to the selected tab
		$tab = $_GET['tab'] ?? '';

		switch ( $tab ) {
			case 'stock':
				require_once plugin_dir_path( __FILE__ ) . 'settings/class-wc-bsale-settings-stock.php';
				break;
			default:
				require_once plugin_dir_path( __FILE__ ) . 'settings/class-wc-bsale-settings-main.php';
				break;
		}

		// Submit button for the form
		submit_button();
		// Close HTML elements of the view
		echo '</form>';
		echo '</div>';
	}
}