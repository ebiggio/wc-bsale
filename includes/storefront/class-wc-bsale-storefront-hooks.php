<?php
/**
 * Hooks for the front-end of the store related to the stock syncing.
 *
 * @class   WC_Bsale_Storefront_Hooks
 * @package WC_Bsale
 */

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Bsale_Storefront_Hooks class
 */
class WC_Bsale_Storefront_Hooks {
	private mixed $storefront_stock_settings;

	public function __construct() {
		$this->storefront_stock_settings = maybe_unserialize( get_option( 'wc_bsale_storefront_stock' ) );

		// Check if any of the stock settings for the storefront side are enabled. If not, we don't need to add the hooks
		if ( ! $this->storefront_stock_settings ) {
			return;
		}

		require_once WC_BSALE_PLUGIN_DIR . '/includes/bsale/class-wc-bsale-api.php';

		if ( isset( $this->storefront_stock_settings['cart'] ) ) {
			add_action( 'woocommerce_add_to_cart', array( $this, 'wc_bsale_add_to_cart_hook' ), 10, 4 );
		}

		if ( isset( $this->storefront_stock_settings['checkout'] ) ) {
			add_action( 'woocommerce_check_cart_items', array( $this, 'wc_bsale_check_cart_items_hook' ) );
		}
	}

	/**
	 * Processes the stock sync when a product is added to the cart.
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

		$bsale_api = new WC_Bsale_API();

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
		$bsale_api = new WC_Bsale_API();

		// Iterate over the cart items
		foreach ( $cart_items as $cart_item_key => $cart_item ) {
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
	 * @param \WC_Bsale\WC_Bsale_API $bsale_api
	 * @param \WC_Product            $product
	 *
	 * @return void
	 */
	private function update_stock_if_needed( WC_Bsale_API $bsale_api, \WC_Product $product ): void {
		// Get the stock of the product in Bsale
		$bsale_stock = $bsale_api->get_stock_by_code( $product->get_sku() );

		// If the product doesn't have a stock in Bsale, we can't sync it
		if ( ! $bsale_stock ) {
			return;
		}

		// If the current stock differs from the stock in Bsale, we sync it
		if ( $product->get_stock_quantity() !== $bsale_stock ) {
			$product->set_stock_quantity( $bsale_stock );
			$product->save();
		}
	}
}

new WC_Bsale_Storefront_Hooks();