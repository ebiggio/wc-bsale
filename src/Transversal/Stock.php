<?php
/**
 * Encompasses the stock consumption hooks that are fired from both the admin and storefront sides.
 *
 * @package WC_Bsale
 * @class   Stock
 */

namespace WC_Bsale\Transversal;

defined( 'ABSPATH' ) || exit;

use WC_Bsale\Bsale\API_Client;
use WC_Bsale\DB_Logger;
use WC_Bsale\Interfaces\API_Consumer;
use WC_Bsale\Interfaces\Observer;

/**
 * Stock class
 */
class Stock implements API_Consumer {
	/**
	 * The observers that will be notified when an event is triggered.
	 *
	 * @see Observer The Observer interface.
	 * @var array
	 */
	private array $observers = array();
	/**
	 * The stock settings, loaded from the Stock class in the admin settings.
	 *
	 * @see \WC_Bsale\Admin\Settings\Stock Stock settings class.
	 * @var array
	 */
	private array $settings;

	public function __construct() {
		// Add the database logger as an observer
		$this->add_observer( DB_Logger::get_instance() );

		$this->settings = \WC_Bsale\Admin\Settings\Stock::get_settings();

		// The stock hooks dependes of an office being set to use it when consuming the stock
		if ( ! $this->settings['office_id'] ) {
			return;
		}

		$this->register_stock_hooks();
	}

	/**
	 * @inheritDoc
	 */
	public function add_observer( Observer $observer ): void {
		$this->observers[] = $observer;
	}

	/**
	 * @inheritDoc
	 */
	public function notify_observers( string $event_trigger, string $event_type, string $identifier, string $message, string $result_code = 'info' ): void {
		foreach ( $this->observers as $observer ) {
			$observer->update( $event_trigger, $event_type, $identifier, $message, $result_code );
		}
	}

	/**
	 * Sets the hooks for stock consumption in Bsale according to the plugin options.
	 *
	 * @return void
	 */
	private function register_stock_hooks(): void {
		if ( 'wc' === $this->settings['transversal']['order_event'] ) {
			// wc = WooCommerce. Meaning that the stock must be consumed when WooCommerce reduces the stock

			// This sends the order object to the callback function
			add_action( 'woocommerce_reduce_order_stock', array( $this, 'check_order_for_stock_consumption' ) );
		} elseif ( 'custom' === $this->settings['transversal']['order_event'] ) {
			// The $order_event is set to "custom", so we hook to each defined order status to consume the stock when an order reaches that status
			$order_status = $this->settings['transversal']['order_status'];

			foreach ( $order_status as $status ) {
				if ( wc_is_order_status( $status ) ) {
					// This sends the ID of the order to the callback function, so we hook to a function that will get the order object
					add_action( 'woocommerce_order_status_' . substr( $status, 3 ), array( $this, 'get_order_for_checking_stock_consumption' ) );
				}
			}
		}
	}

	/**
	 * Gets the order object and sends it to the function that will check if the stock was consumed in Bsale.
	 *
	 * @param int $order_id The ID of the order to be checked.
	 *
	 * @return void
	 */
	public function get_order_for_checking_stock_consumption( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$this->check_order_for_stock_consumption( $order );

	}

	/**
	 * Check an order's products to see if their stock was consumed in Bsale. If not, consume it.
	 *
	 * @param object $order The WooCommerce order object that contains the products that will be checked.
	 *
	 * @return void
	 */
	public function check_order_for_stock_consumption( object $order ): void {
		if ( ! $order ) {
			return;
		}

		$products_to_consume_stock = array();
		$items_to_update_meta      = array();

		foreach ( $order->get_items() as $item ) {
			// Get the product
			$product = $item->get_product();

			// Check if the product is grouped. If it is, we need to consume the stock of each product in the group
			if ( $product->is_type( 'grouped' ) ) {
				$grouped_products = $product->get_children();

				foreach ( $grouped_products as $grouped_product_id ) {
					// TODO Pending logic to consume stock of grouped products
					// TODO Check how the meta key behaves in this case. A customer could buy the same product more than once in the same order: one in a grouped product and another as a single product
					$grouped_product = wc_get_product( $grouped_product_id );
				}
			} else {
				// Check if the stock was already consumed in Bsale
				if ( $item->get_meta( '_wc_bsale_stock_consumed' ) ) {
					continue;
				}

				$products_to_consume_stock[] = array(
					'identifier' => $product->get_sku(),
					'quantity'   => $item->get_quantity(),
				);

				$items_to_update_meta[] = $item;
			}
		}

		if ( empty( $products_to_consume_stock ) ) {
			return;
		}

		// Consume stock in Bsale
		if ( $this->consume_bsale_stock( $order->get_order_number(), $products_to_consume_stock ) ) {
			// Set a custom meta key to indicate that the stock was consumed in Bsale, with the timestamp of the operation
			$operation_timestamp = current_time( 'timestamp', true );

			foreach ( $items_to_update_meta as $item ) {
				$item->add_meta_data( '_wc_bsale_stock_consumed', $operation_timestamp, true );
				$item->save_meta_data();
			}
		}
	}

	/**
	 * Consumes stock of products in Bsale according to the quantity specified.
	 *
	 * @param int   $order_number              The order number. Will be used for the log message.
	 * @param array $products_to_consume_stock An array of products to consume the stock from, each with the product's SKU and the quantity to consume.
	 *
	 * @return bool True if the stock was consumed successfully for all the provided products. False if an empty office ID was provided, or if there was an error consuming the stock.
	 */
	private function consume_bsale_stock( int $order_number, array $products_to_consume_stock ): bool {
		$office_id = $this->settings['office_id'];

		$note           = strip_tags( $this->settings['transversal']['note'] );
		$formatted_note = str_replace( array( '{1}', '{2}', "\r", "\n" ), array( get_bloginfo( 'name' ), $order_number, '', '' ), $note );

		$bsale_api_client     = new API_Client();
		$bsale_stock_consumed = $bsale_api_client->consume_stock( $formatted_note, $office_id, $products_to_consume_stock );

		if ( $bsale_stock_consumed ) {
			$this->notify_observers( 'stock_consumption', 'consume_bsale_stock', $order_number, 'Stock successfully consumed in Bsale', 'success' );
		} else {
			$bsale_error = $bsale_api_client->get_last_wp_error();

			$this->notify_observers( 'stock_consumption', 'consume_bsale_stock', $order_number, 'Error consuming stock in Bsale: ' . $bsale_error->get_error_message(), 'error' );
		}

		return $bsale_stock_consumed;
	}
}