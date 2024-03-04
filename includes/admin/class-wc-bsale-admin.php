<?php

defined( 'ABSPATH' ) || exit;

class WC_Bsale_Admin {
	private $settings;

	public function __construct() {
		require_once plugin_dir_path( __FILE__ ) . 'class-wc-bsale-admin-settings.php';

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		$this->settings = new WC_Bsale_Admin_Settings();
	}

	public function add_admin_menu() {
		add_menu_page(
			'WC Bsale Settings', // Page title
			'WC Bsale', // Menu title
			'manage_options', // Capability
			'wc_bsale_settings_page', // Menu slug
			array( $this->settings, 'settings_page_content' ), // Callback
			'dashicons-admin-generic' // Icon URL
		);
	}
}

new WC_Bsale_Admin();