<?php
/**
 * Settings manager for the plugin.
 *
 * @class   Settings_Manager
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin;

defined( 'ABSPATH' ) || exit;

use const WC_Bsale\PLUGIN_URL;
use const WC_Bsale\PLUGIN_VERSION;

/**
 * Settings_Manager class
 */
class Settings_Manager {
	private array $settings_classes_map = array();

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
		$this->settings_classes_map = array(
			'main'    => new Settings\Main_Settings(),
			'stock'   => new Settings\Stock_Settings(),
			'invoice' => new Settings\Invoice_Settings(),
			'cron'    => new Settings\Cron_Settings()
		);

		register_setting( 'wc_bsale_main_settings_group', 'wc_bsale_main', array( $this->settings_classes_map['main'], 'validate_settings' ) );

		register_setting( 'wc_bsale_stock_settings_group', 'wc_bsale_stock', array( $this->settings_classes_map['stock'], 'validate_settings' ) );

		register_setting( 'wc_bsale_invoice_settings_group', 'wc_bsale_invoice', array( $this->settings_classes_map['invoice'], 'validate_settings' ) );

		register_setting( 'wc_bsale_cron_settings_group', 'wc_bsale_cron', array( $this->settings_classes_map['cron'], 'validate_settings' ) );
	}

	/**
	 * Displays a settings page according to the selected tab.
	 *
	 * @return void
	 */
	public function display_settings(): void {
		// Check if the user has the necessary permissions to access the settings
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Set a success message if the settings were saved
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_message', 'Settings saved', 'updated' );
		}

		$current_tab = $_GET['tab'] ?? 'main'; // Fallback to 'main' if 'tab' is not set

		// Check if the API access token is set and redirect to the main settings if trying to access another tab
		if ( ! $this->settings_classes_map['main']->get_access_token() && 'main' !== $current_tab ) {
			// Show a warning message if the API access token is not set
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_message', 'The Bsale API access token is required.' );

			// "Redirect" to the main settings page
			$current_tab = 'main';
		}

		// Include the view that contains the tabs for all the settings
		include plugin_dir_path( __FILE__ ) . 'Settings/Views/Header.php';

		if ( array_key_exists( $current_tab, $this->settings_classes_map ) ) {
			$this->settings_classes_map[ $current_tab ]->display_settings_page();
		} else {
			// Handle the case where $current_tab does not match any known option
			$this->settings_classes_map['main']->display_settings_page();
		}

		// Submit button for the form
		submit_button();
		// Close HTML elements of the view
		echo '</form>';
		echo '</div>';
	}
}