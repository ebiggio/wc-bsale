<?php

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

class WC_Bsale_Admin {
	private $settings;

	public function __construct() {
		// If the plugin is active, load the admin settings and the hooks
		if ( in_array( 'wc-bsale/wc-bsale.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-wc-bsale-admin-settings.php';

			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			$this->settings = new WC_Bsale_Admin_Settings();

			require_once plugin_dir_path( __FILE__ ) . 'class-wc-bsale-admin-hooks.php';
			new WC_Bsale_Admin_Hooks();
		}
	}

	public function add_admin_menu() {
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