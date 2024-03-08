<?php
/**
 * Main settings page
 *
 * Manages the main settings page of the plugin
 *
 * @package WC_Bsale
 */

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Bsale_Admin_Settings_Main class
 */
class WC_Bsale_Admin_Settings_Main {
	/**
	 * Manages the main settings page
	 *
	 * @return void
	 */
	public function main_settings_page_content(): void {
		add_settings_section(
			'wc_bsale_main_section',
			'Bsale API configuration',
			array( $this, 'settings_section_description' ),
			'wc-bsale-settings'
		);

		add_settings_field(
			'wc_bsale_sandbox_access_token',
			'Sandbox access token',
			array( $this, 'settings_field_callback' ),
			'wc-bsale-settings',
			'wc_bsale_main_section'
		);

		settings_fields( 'wc_bsale_main_settings_group' );
		do_settings_sections( 'wc-bsale-settings' );
	}

	public function settings_section_description(): void {
		echo '<p>Settings for connecting with the Bsale API.</p>';
	}

	public function settings_field_callback(): void {
		echo '<input type="text" name="wc_bsale_sandbox_access_token" value="' . esc_attr( get_option( 'wc_bsale_sandbox_access_token' ) ) . '" style="width: 100%;" />';
	}
}

$wc_bsale_admin_settings_main = new WC_Bsale_Admin_Settings_Main();
$wc_bsale_admin_settings_main->main_settings_page_content();