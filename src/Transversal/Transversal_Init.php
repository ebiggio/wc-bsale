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
	private array $transversal_settings;

	public function __construct() {
		$this->transversal_settings = $this->get_transversal_settings();

		// Add the hooks after WooCommerce is loaded
		add_action( 'woocommerce_loaded', array( $this, 'init_hooks' ) );

		// Hide the meta data from the order items
		add_filter( 'woocommerce_hidden_order_itemmeta', function ( $hidden_order_itemmeta ) {
			$hidden_order_itemmeta[] = '_wc_bsale_stock_consumed';

			return $hidden_order_itemmeta;
		} );
	}

	/**
	 * Gets the settings that govern the transversal hooks. Will return default settings if the settings are not set.
	 *
	 * @return array[] The settings for the transversal hooks.
	 */
	private function get_transversal_settings(): array {
		$default_stock_settings = array(
			'stock' =>
				array(
					'office_id'    => 0,
					'order_event'  => '',
					'order_status' => array(),
					'note'         => ''
				),
		);

		$stock_settings             = maybe_unserialize( get_option( 'wc_bsale_stock' ) );
		$transversal_stock_settings = $stock_settings['transversal'] ?? $default_stock_settings;

		return array(
			'stock'   =>
				array(
					'office_id'    => (int) $stock_settings['office_id'],
					'order_event'  => $transversal_stock_settings['order_event'],
					'order_status' => maybe_unserialize( $transversal_stock_settings['order_status'] ) ?? array(),
					'note'         => $transversal_stock_settings['note']
				)
		);
	}

	/**
	 * Loads the classes that will handle the transversal hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		new Stock( $this->transversal_settings['stock'] );
		new Invoice();
	}
}