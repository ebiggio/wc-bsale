<?php
/*
Plugin Name: WooCommerce Bsale Integration
Plugin URI: https://github.com/ebiggio/wc-bsale
Description: WooCommerce plugin to integrate with the Bsale system, allowing you to sync product stocks, prices and generate electronic invoices.
Version: 0.1.0
Author: Enzo Biggio
Author URI: https://github.com/ebiggio/wc-bsale
License: GPL3
Text Domain: wc-bsale
*/

namespace WC_Bsale;

// Prevent direct access to this file
defined( 'ABSPATH' ) || exit;

const WC_BSALE_PLUGIN_VERSION = '0.1.0';
const WC_BSALE_PLUGIN_DIR = __DIR__;
define( "WC_Bsale\WC_BSALE_PLUGIN_URL", plugin_dir_url( __FILE__ ) );

// Load the plugin text domain
load_plugin_textdomain( 'wc_bsale', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	add_action( 'admin_notices', function () {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html__( 'WooCommerce Bsale Integration requires WooCommerce to be installed and active.', 'wc-bsale' ); ?></p>
		</div>
		<?php
	} );

	return;
}

// Check if this plugin is active
if ( in_array( 'wc-bsale/wc-bsale.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	// And away we go
	if ( is_admin() ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-wc-bsale-admin.php';
	}
}