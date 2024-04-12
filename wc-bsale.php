<?php
/*
Plugin Name: WooCommerce Bsale Integration for Chile
Plugin URI: https://github.com/ebiggio/wc-bsale
Description: WooCommerce plugin to integrate with the Bsale system, allowing you to sync product stocks, prices and generate electronic invoices.
Version: 0.5.0
Author: Enzo Biggio
Author URI: https://github.com/ebiggio/wc-bsale
License: GPL3
Text Domain: wc-bsale
*/

namespace WC_Bsale;

// Prevent direct access to this file
defined( 'ABSPATH' ) || exit;

const PLUGIN_VERSION = '0.7.0';
const PLUGIN_DIR     = __DIR__;
define( "WC_Bsale\PLUGIN_URL", plugin_dir_url( __FILE__ ) );

// Load the plugin text domain TODO: Add translations
// load_plugin_textdomain( 'wc_bsale', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

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

// Install class
register_activation_hook( __FILE__, function () {
	require_once plugin_dir_path( __FILE__ ) . '/src/Installer.php';
} );

// Autoloader
require_once plugin_dir_path( __FILE__ ) . '/src/Autoload.php';

// Expose the hook for the cron job
add_action( 'wc_bsale_cron', array( new Cron(), 'run_sync' ) );

// And away we go
// --------------
// Load the transversal hooks, which are hooks that can be fired both from the storefront or the admin side
new Transversal\Transversal_Init();

if ( is_admin() ) {
	new Admin\Admin_Init();
} else {
	new Storefront\Storefront_Init();
}