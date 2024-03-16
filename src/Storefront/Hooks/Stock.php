<?php
/**
 * Hooks for the front-end of the store related to the stock syncing.
 *
 * @class   Stock
 * @package WC_Bsale
 */

namespace WC_Bsale\Storefront\Hooks;

use WC_Bsale\Bsale_API_Client;
use WC_Bsale\DB_Logger;
use WC_Bsale\Interfaces\API_Consumer;
use WC_Bsale\Interfaces\Observer;

defined( 'ABSPATH' ) || exit;

/**
 * Stock class
 *
 * Implements the API_Consumer interface to notify the observers the results of the stock sync operations.
 * For now, it only has the observer for the database logger, but it could have more in the future.
 */
class Stock implements API_Consumer {
	private mixed $storefront_stock_settings;
	private array $observers = array();

	public function __construct() {
		$this->storefront_stock_settings = maybe_unserialize( get_option( 'wc_bsale_storefront_stock' ) );

		// Check if any of the stock settings for the storefront side are enabled. If not, we don't need to add the hooks
		if ( ! $this->storefront_stock_settings ) {
			return;
		}

		if ( isset( $this->storefront_stock_settings['cart'] ) ) {
			add_action( 'woocommerce_add_to_cart', array( $this, 'wc_bsale_add_to_cart_hook' ), 10, 4 );
		}

		if ( isset( $this->storefront_stock_settings['checkout'] ) ) {
			add_action( 'woocommerce_check_cart_items', array( $this, 'wc_bsale_check_cart_items_hook' ) );
		}

		// Add the database logger as an observer
		$this->add_observer( DB_Logger::get_instance() );
	}

	/**
	 * Adds an observer to the list of observers.
	 *
	 * @param \WC_Bsale\Interfaces\Observer $observer The observer to add.
	 *
	 * @return void
	 */
	public function add_observer( Observer $observer ): void {
		$this->observers[] = $observer;
	}

	/**
	 * Notifies all the observers of an event.
	 *
	 * @param string $event_trigger
	 * @param string $event_type
	 * @param string $identifier
	 * @param string $message
	 * @param string $result_code
	 *
	 * @return void
	 */
	public function notify_observers( string $event_trigger, string $event_type, string $identifier, string $message, string $result_code = 'info' ): void {
		foreach ( $this->observers as $observer ) {
			$observer->update( $event_trigger, $event_type, $identifier, $message, $result_code );
		}
	}

	/**
	 * Processes the stock sync when a product is added to the cart.
	 *
	 * The hook "woocommerce_add_to_cart" is triggered when a product is added to the cart. We use it here to sync the stock of the product with Bsale.
	 * For that purpose, we only need the product ID or the variation ID, but the other parameters are automagically passed by WooCommerce by the hook
	 * in this order.
	 *
	 * @param $cart_item_key
	 * @param $product_id
	 * @param $quantity
	 * @param $variation_id
	 *
	 * @return void
	 */
	public function wc_bsale_add_to_cart_hook( $cart_item_key, $product_id, $quantity, $variation_id ): void {
		// Get the product object that was added to the cart
		$product = wc_get_product( $product_id );

		// If the product is a variable product, we need to get the variation object
		if ( $product->is_type( 'variable' ) ) {
			$product = wc_get_product( $variation_id );
		}

		// Check if the product is valid for syncing
		if ( ! $this->is_valid_for_sync( $product ) ) {
			return;
		}

		$bsale_api = new Bsale_API_Client;

		$this->update_stock_if_needed( $bsale_api, $product );
	}

	/**
	 * Processes the stock sync during the checkout process.
	 *
	 * The hook "woocommerce_check_cart_items" is actually triggered when the items in the cart are checked, which occurs in different parts of the store and
	 * not only in the checkout page. It is, however, the most reliable way to sync the stock during the start of the checkout process, so we use it here
	 * conditioned to only run when we are on the checkout page.
	 *
	 * @return void
	 */
	public function wc_bsale_check_cart_items_hook(): void {
		// We only want to check the cart items if we are in the checkout page
		if ( ! is_checkout() ) {
			return;
		}

		// Get the cart object
		$cart = WC()->cart;

		// Get the cart items
		$cart_items = $cart->get_cart();

		// Get the Bsale API object
		$bsale_api = new Bsale_API_Client;

		// Iterate over the cart items
		foreach ( $cart_items as $cart_item ) {
			// Get the product object
			$product = $cart_item['data'];

			// If the product is a variable product, we need to get the variation object
			if ( $product->is_type( 'variation' ) ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			}

			// Check if the product is valid for syncing
			if ( ! $this->is_valid_for_sync( $product ) ) {
				continue;
			}

			$this->update_stock_if_needed( $bsale_api, $product );
		}
	}

	/**
	 * Checks if a product is valid for syncing its stock with Bsale.
	 *
	 * For a product to be valid for syncing, it must:
	 * - Be marked with the "Manage stock" option
	 * - Have a SKU
	 *
	 * @param $product \WC_Product The product object to check.
	 *
	 * @return bool True if the product is valid for syncing, false otherwise.
	 */
	private function is_valid_for_sync( \WC_Product $product ): bool {
		// If the product is not marked with the "Manage stock" option, we don't need to sync it
		if ( ! $product->managing_stock() ) {
			return false;
		}

		// Get the SKU of the product
		$sku = $product->get_sku();

		// If the product doesn't have a SKU, we can't sync it
		if ( empty( $sku ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Updates the stock of a product in WooCommerce if it differs from the stock in Bsale.
	 *
	 * @param Bsale_API_Client $bsale_api The Bsale API client object.
	 * @param \WC_Product      $product   The product object.
	 *
	 * @return void
	 */
	private function update_stock_if_needed( Bsale_API_Client $bsale_api, \WC_Product $product ): void {
		// Get the stock of the product in Bsale
		try {
			$bsale_stock = $bsale_api->get_stock_by_code( $product->get_sku() );
		} catch ( \Exception $e ) {
			$this->notify_observers( 'wc_bsale_check_cart_items_hook', 'stock_update', $product->get_sku(), $e->getMessage(), 'error' );

			return;
		}

		// If the product doesn't have a stock in Bsale, we can't sync it
		if ( ! $bsale_stock ) {
			$this->notify_observers( 'wc_bsale_check_cart_items_hook', 'stock_update', $product->get_sku(), 'The product does not have a stock in Bsale', 'warning' );

			return;
		}

		// If the current stock differs from the stock in Bsale, we sync it
		if ( $product->get_stock_quantity() !== $bsale_stock ) {
			$product->set_stock_quantity( $bsale_stock );
			$product->save();

			$this->notify_observers( 'wc_bsale_check_cart_items_hook', 'stock_update', $product->get_sku(), 'Stock updated to [' . $bsale_stock . ']', 'success' );
		}
	}
}