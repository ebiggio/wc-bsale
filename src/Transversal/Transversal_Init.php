<?php
/**
 * Initializes the transversal hooks.
 *
 * @package WC_Bsale
 * @class   Transversal_Init
 */

namespace WC_Bsale\Transversal;

defined( 'ABSPATH' ) || exit;

/**
 * Transversal_Init class
 */
class Transversal_Init {
	public function __construct() {
		// Add the hooks after WooCommerce is loaded
		add_action( 'woocommerce_loaded', array( $this, 'init_hooks' ) );

		// Hide the meta data from the order items
		add_filter( 'woocommerce_hidden_order_itemmeta', function ( $hidden_order_itemmeta ) {
			$hidden_order_itemmeta[] = '_wc_bsale_stock_consumed';

			return $hidden_order_itemmeta;
		} );
	}

	/**
	 * Loads the classes that will handle the transversal hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		new Stock();
		new Invoice();
	}
}