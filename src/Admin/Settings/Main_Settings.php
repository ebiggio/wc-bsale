<?php
/**
 * Main settings page.
 *
 * @class   Main_Settings
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Main_Settings class
 */
class Main_Settings {
	public function __construct() {
		$this->settings_page_content();
	}

	/**
	 * Displays the main settings page.
	 *
	 * @return void
	 */
	public function settings_page_content(): void {
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