<?php
/**
 * Hooks for when viewing an order's items in the admin.
 *
 * @package WC_Bsale
 * @class   Order_Items
 */

namespace WC_Bsale\Admin\Hooks;

use const WC_Bsale\PLUGIN_URL;
use const WC_Bsale\PLUGIN_VERSION;

defined( 'ABSPATH' ) || exit;

/**
 * Order_Items class
 */
class Order_Items {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'woocommerce_before_order_itemmeta', array( $this, 'display_bsale_stock_consumption_status' ), 10, 2 );
	}

	/**
	 * Enqueues the assets for the stock consumption status in the order items.
	 *
	 * @param string $hook_suffix The current page hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		global $post_type;
		if ( 'post.php' === $hook_suffix && 'shop_order' === $post_type ) {
			wp_enqueue_style( 'wc-bsale-admin', PLUGIN_URL . 'assets/css/wc-bsale.css', array(), PLUGIN_VERSION );
		}
	}

	/**
	 * Display the stock consumption status in the order items.
	 *
	 * @throws \Exception
	 */
	public function display_bsale_stock_consumption_status( $item_id, $item ): void {
		/*
		 * We must only display the stock consumption status in the admin side. While currently, the "woocommerce_before_order_itemmeta" hook is only fired in the admin side,
		 * we add this check to prevent any issues in the future (or if a theme incorrectly decides to fire this hook in the storefront).
		 */
		if ( ! is_admin() ) {
			return;
		}

		$consumption_timestamp = $item->get_meta( '_wc_bsale_stock_consumed', true );

		if ( $consumption_timestamp ) {
			// Format the date and time of the stock consumption, according to WordPress timezone and WooCommerce view settings
			$timezone_string  = get_option( 'timezone_string' ) ?: 'UTC';
			$consumption_date = new \WC_DateTime();
			$consumption_date->setTimestamp( $consumption_timestamp );
			$consumption_date->setTimezone( new \DateTimeZone( $timezone_string ) );

			echo '<span class="wc-bsale-stock-consumed"><img src="' . PLUGIN_URL . '/assets/images/bsale_icon_bw.png" alt="" height="10px"> '
			     . __( 'Stock consumed in Bsale on ', 'wc-bsale' )
			     . wc_format_datetime( $consumption_date ) . ' @ ' . $consumption_date->format( wc_time_format() )
			     . '</span>';
		}
	}
}