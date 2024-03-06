<?php

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

class WC_Bsale_Admin_Settings {
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init_settings' ) );
	}

	public function init_settings() {
		// Register a settings section
		add_settings_section(
			'wc_bsale_main_section', // ID
			'Main Settings', // Title
			array( $this, 'settings_section_description' ), // Callback
			'wc_bsale_settings_page' // Page
		);

		// Register a new field in the section
		add_settings_field(
			'wc_bsale_sandbox_access_token', // ID
			'Sandbox Access Token', // Title
			array( $this, 'settings_field_callback' ), // Callback
			'wc_bsale_settings_page', // Page
			'wc_bsale_main_section' // Section
		);

		// Register the setting so WordPress knows to handle our settings
		register_setting( 'wc_bsale_settings_group', 'wc_bsale_sandbox_access_token' );
	}

	public function settings_page_content() {
		?>
		<div class="wrap">
			<h2>WC Bsale Settings</h2>
			<form action="options.php" method="POST">
				<?php
				settings_fields( 'wc_bsale_settings_group' );
				do_settings_sections( 'wc_bsale_settings_page' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function settings_section_description() {
		echo '<p>Main settings for the WC Bsale plugin.</p>';
	}

	public function settings_field_callback() {
		$setting = get_option( 'wc_bsale_sandbox_access_token' );
		echo '<input type="text" name="wc_bsale_sandbox_access_token" value="' . esc_attr( $setting ) . '"/>';
	}
}