<?php
/**
 * Handles the cron functionality of the plugin.
 *
 * @class   Cron
 * @package WC_Bsale
 */

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

use WC_Bsale\Bsale\API_Client;
use WC_Bsale\Interfaces\API_Consumer;
use WC_Bsale\Interfaces\Observer;

/**
 * Cron class
 */
class Cron implements API_Consumer {
	/**
	 * The observers that will be notified when an event is triggered.
	 *
	 * @see Observer The observer interface.
	 * @var array
	 */
	private array $observers = array();
	/**
	 * The cron settings, loaded from the Cron class in the admin settings.
	 *
	 * @see \WC_Bsale\Admin\Settings\Cron Cron settings class.
	 * @var array
	 */
	private array $cron_settings;
	/**
	 * The stock settings, loaded from the Stock class in the admin settings.
	 * These settings are needed for the office ID to sync the stock.
	 *
	 * @see \WC_Bsale\Admin\Settings\Stock Stock settings class.
	 * @var array
	 */
	private array $stock_settings;
	/**
	 * The invoice settings, loaded from the Invoice class in the admin settings.
	 * These settings are needed for the price list ID to sync the prices.
	 * If it's not set, the default price list of the office used for the stock sync will be used.
	 *
	 * @see \WC_Bsale\Admin\Settings\Invoice Invoice settings class.
	 * @var array
	 */
	private array $invoice_settings;
	/**
	 * The invoker of the cron process. This is used to identify the source of the cron process in the logs. Default is 'wp-cron'.
	 *
	 * @var string
	 */
	private string $invoker = 'wp-cron';

	public function __construct() {
		// Add the database logger as an observer
		$this->add_observer( DB_Logger::get_instance() );

		$this->cron_settings    = Admin\Settings\Cron::get_settings();
		$this->stock_settings   = Admin\Settings\Stock::get_settings();
		$this->invoice_settings = Admin\Settings\Invoice::get_settings();

		// Add the plugin query var to the query vars list if the cron mode is set to "external"
		if ( 'external' == $this->cron_settings['mode'] ) {
			add_filter( 'query_vars', function ( $query_vars ) {
				$query_vars[] = 'wc_bsale';

				return $query_vars;
			} );
		}

		add_action( 'template_redirect', array( $this, 'cron_endpoint' ) );
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
	 * Wrapper method to notify the observers for the cron process, that sets the $event_trigger to the invoker of the cron process.
	 *
	 * @param string $event_type  The type of the event (stock update, price update, etc.). Could also be an empty string if the event is not related to a specific product or variation.
	 * @param string $identifier  The identifier of the event, usually a product or variation SKU.
	 * @param string $message     The message to log.
	 * @param string $result_code The result code of the operation. Can only be 'info', 'success', 'warning' or 'error'.
	 *
	 * @return void
	 */
	private function notify_observers_for_cron( string $event_type, string $identifier, string $message, string $result_code = 'info' ): void {
		$this->notify_observers( $this->invoker, $event_type, $identifier, $message, $result_code );
	}

	/**
	 * Endpoint for the cron job.
	 *
	 * Captures a GET request with the "wc_bsale" query var and the "run_cron" value to run the cron job, if the cron mode is set to "external".
	 * Will check the secret key to avoid unauthorized access.
	 *
	 * @return void This method will stop the execution of the script after sending an HTTP response code, without displaying any content.
	 */
	public function cron_endpoint(): void {
		if ( 'external' === $this->cron_settings['mode'] && get_query_var( 'wc_bsale' ) ) {
			// Check the secret key to avoid unauthorized access
			if ( 'run_cron' !== $_GET['wc_bsale'] || ! isset( $_GET['secret_key'] ) || $_GET['secret_key'] != $this->cron_settings['secret_key'] ) {
				wp_die( 'Unauthorized access to the cron endpoint.', 'wc-bsale', array( 'response' => 403 ) );
			}

			// Set the invoker to 'cron', since this is an external cron call (not WP-Cron)
			$this->invoker = 'cron';

			$product_or_variation_updated = $this->run_sync();
			// Send a 204 status code if a product or variation was updated, or a 200 status code if not
			http_response_code( $product_or_variation_updated ? 204 : 200 );

			// We don't use wp_die() here because its intended to be used when an error occurs. We just want to stop the execution
			exit();
		}
	}

	/**
	 * Executes the cron sync of the plugin.
	 *
	 * This method will sync WooCommerce products with Bsale according to the settings.
	 * Depending on the cron configuration, it could sync all the products or only specific ones.
	 * The settings could also list products or variations to be excluded from the sync process, which will be skipped during the process.
	 * For a product or variation to be synced, it must have a SKU. If not, it will be skipped and a warning will be logged.
	 *
	 * The sync process could update:
	 * - The status of the product (active or inactive)
	 * - The description of the product
	 * - The stock of the product or variation - This requires an office ID set in the stock settings, and the product must have the "Manage stock" option enabled
	 * - The **regular** price of the product or variation - This requires a price list ID set in the invoice settings, or the default price list of the office used for the stock sync will be used
	 *
	 * Each of these updates can be enabled or disabled in the settings.
	 *
	 * Only when a value is different from the one in Bsale will a product or variation be updated in WooCommerce.
	 *
	 * @return bool True if at least one product or variation was updated during the sync process, false otherwise.
	 *              A product or variation is considered updated if its status, description, stock or regular price was updated.
	 *              A return value of false doesn't mean that the process failed, but that no product or variation was updated.
	 */
	public function run_sync(): bool {
		// Check if the cron job is enabled
		if ( ! $this->cron_settings['enabled'] ) {
			return false;
		}

		$this->notify_observers_for_cron( '', '', __( 'Starting the cron process', 'wc-bsale' ) );

		$script_start_time = microtime( true );

		// If the sync mode is set to specific products, check if there are products configured to be synced
		if ( 'specific' == $this->cron_settings['catalog'] ) {
			$products_id = $this->cron_settings['products'];

			if ( empty( $products_id ) ) {
				$this->notify_observers_for_cron( '', '', __( 'Cron settings set to sync specific products, but no products are configured', 'wc-bsale' ), 'warning' );

				return false;
			}

			// Get the products from the store
			$products = wc_get_products( array( 'include' => $products_id ) );
		} else {
			// The sync mode is set to all products, so we get all the products from the store
			$products = wc_get_products( array( 'orderby' => 'date', 'order' => 'DESC' ) );
		}

		// If there are no products to sync, we stop the process
		if ( empty( $products ) ) {
			$this->notify_observers_for_cron( '', '', __( 'No products were found to sync', 'wc-bsale' ), 'warning' );

			return false;
		}

		$bsale_api_client = new API_Client();

		// This variable will be used to check if at least one product or variation was updated during the sync process. If so, this flag will be set to true
		// This is intended to help determine if the cron process updated any product or variation, since this flag will be returned to the caller
		$product_or_variation_updated = false;

		// Check if a price list ID is set in the settings. If not, get the default price list for the office of the stock settings
		$price_list_id = (int) $this->invoice_settings['price_list_id'] ?? null;

		if ( ! $price_list_id ) {
			$office_id = (int) $this->stock_settings['office_id'] ?? null;

			if ( $office_id ) {
				try {
					$office_details = $bsale_api_client->get_office_by_id( $office_id );
					$price_list_id  = $office_details['defaultPriceList'];
				} catch ( \Exception $e ) {
					$this->notify_observers_for_cron( '', '', 'Error getting the office details to obtain its price list: ' . $e->getMessage(), 'error' );
				}
			}
		}

		// Iterate over the products and sync them with Bsale
		foreach ( $products as $product ) {
			// Check if the product is in the excluded list. If so, we skip it from the sync process
			if ( in_array( $product->get_id(), $this->cron_settings['excluded_products'] ) ) {
				continue;
			}

			// Check if the product has a SKU. If not, we skip it from the sync process
			if ( ! $product->get_sku() ) {
				$this->notify_observers_for_cron( '', '', sprintf( __( 'Product [%s] has no SKU; skipping', 'wc-bsale' ), $product->get_name() ), 'warning' );

				continue;
			}

			// Get the Bsale variation data by the product SKU. In Bsale, a variation is a product
			try {
				$bsale_product = $bsale_api_client->get_variant_by_identifier( $product->get_sku() );
			} catch ( \Exception $e ) {
				$this->notify_observers_for_cron( 'get_bsale_variant', $product->get_sku(), $e->getMessage(), 'error' );

				continue;
			}

			// If the product is not found in Bsale, we skip all the sync process and notify the observers
			if ( ! $bsale_product ) {
				$this->notify_observers_for_cron( 'get_bsale_variant', $product->get_sku(), __( 'Product not found in Bsale', 'wc-bsale' ), 'warning' );

				continue;
			}

			$save_product = false;

			// Check if we need to sync the status of the product, according to the settings
			if ( isset( $this->cron_settings['fields']['status'] ) ) {
				/*
				 * Check if the status of the product is different from the one in Bsale. If so, update the WooCommerce product
				 * In Bsale, a status of 0 means active
				 * Bsale uses the term "state" instead of "status", which is what WooCommerce uses. We assign it to a variable to make the code more readable
				 */
				$bsale_status = $bsale_product['state'] == 0 ? 'publish' : 'draft';

				if ( $product->get_status() != $bsale_status ) {
					$previous_status = $product->get_status();
					$new_status      = $bsale_status;

					$product->set_status( $new_status );
					$save_product = true;

					$this->notify_observers_for_cron( 'status_update', $product->get_sku(), sprintf( __( 'Product status updated from [%s] to [%s]', 'wc-bsale' ), $previous_status, $new_status ), 'success' );
				}
			}

			// Check if we need to sync the description of the product, according to the settings
			if ( isset( $this->cron_settings['fields']['description'] ) ) {
				// Check if the description of the product is different from the one in Bsale. If so, update the WooCommerce product
				if ( $product->get_description() != $bsale_product['description'] ) {
					$product->set_description( esc_html( $bsale_product['description'] ) );
					$save_product = true;

					$this->notify_observers_for_cron( 'description_update', $product->get_sku(), __( 'Product description updated', 'wc-bsale' ), 'success' );
				}
			}

			// Check if we need to sync the stock or price of the product, according to the settings
			$sync_stock = isset( $this->cron_settings['fields']['stock'] );
			$sync_price = isset( $this->cron_settings['fields']['price'] ) && $price_list_id;

			if ( $sync_stock || $sync_price ) {
				// For the stock sync, there must be an office ID set in the stock settings
				if ( ! $this->stock_settings['office_id'] ) {
					$this->notify_observers_for_cron( '', '', __( 'No office ID set in the stock settings for stock sync', 'wc-bsale' ), 'warning' );

					$sync_stock = false;
				}

				// If the product is a variable product, we must update its variations
				if ( $product->is_type( 'variable' ) ) {
					$variations = $product->get_children();

					// A variation could also be in the excluded list, so we check it here
					$variations = array_diff( $variations, $this->cron_settings['excluded_products'] );

					foreach ( $variations as $variation_id ) {
						$variation = wc_get_product( $variation_id );

						if ( $sync_stock ) {
							$stock_updated = $this->process_stock_sync( $variation, $bsale_api_client );

							if ( $stock_updated ) {
								$product_or_variation_updated = true;
							}
						}

						if ( $sync_price ) {
							$price_updated = $this->process_price_sync( $price_list_id, $variation, $bsale_api_client );

							if ( $price_updated ) {
								$product_or_variation_updated = true;
							}
						}
					}
				} else {
					// The product is not a variable product, so we update its stock and price directly
					if ( $sync_stock ) {
						$stock_updated = $this->process_stock_sync( $product, $bsale_api_client );

						if ( $stock_updated ) {
							$product_or_variation_updated = true;
						}
					}

					if ( $sync_price ) {
						$price_updated = $this->process_price_sync( $price_list_id, $product, $bsale_api_client );

						if ( $price_updated ) {
							$product_or_variation_updated = true;
						}
					}
				}
			}

			// If the product status or description was updated, we save the product
			// This is done at the end to avoid saving more than necessary, but if the stock or price was updated, the product was already saved nevertheless
			if ( $save_product ) {
				$product->save();

				$product_or_variation_updated = true;
			}
		}

		$this->notify_observers_for_cron( '', '', __( 'Cron process finished in ' . number_format( microtime( true ) - $script_start_time, 2 ) . ' seconds', 'wc-bsale' ) );

		return $product_or_variation_updated;
	}

	/**
	 * Process the stock sync of a product or variation.
	 *
	 * @param \WC_Product                $product          The product or variation to sync.
	 * @param \WC_Bsale\Bsale\API_Client $bsale_api_client The Bsale API client object.
	 *
	 * @return bool True if the stock was updated, false otherwise.
	 */
	private function process_stock_sync( \WC_Product $product, API_Client $bsale_api_client ): bool {
		if ( ! $product->managing_stock() ) {
			$this->notify_observers_for_cron( 'stock_update', $product->get_sku(), __( '"Manage stock" option disabled; skipping', 'wc-bsale' ) );

			return false;
		}

		try {
			$bsale_stock = $bsale_api_client->get_stock_by_identifier( $product->get_sku(), $this->stock_settings['office_id'] );
		} catch ( \Exception $e ) {
			$this->notify_observers_for_cron( 'stock_update', $product->get_sku(), $e->getMessage(), 'error' );

			return false;
		}

		if ( ! $bsale_stock ) {
			$this->notify_observers_for_cron( 'stock_update', $product->get_sku(), __( 'No stock found in Bsale', 'wc-bsale' ), 'warning' );

			return false;
		}

		$current_stock = $product->get_stock_quantity();

		// Check if the stock of the producto is different from the one in Bsale. If so, update the WooCommerce stock
		if ( $current_stock != $bsale_stock ) {
			$product->set_stock_quantity( $bsale_stock );
			$product->save();

			$this->notify_observers_for_cron( 'stock_update', $product->get_sku(), sprintf( __( 'Stock updated from %s to %s', 'wc-bsale' ), $current_stock, $bsale_stock ), 'success' );

			return true;
		}

		return false;
	}

	/**
	 * Process the price sync of a product or variation.
	 *
	 * @param int                        $price_list_id    The price list ID to get the price from Bsale.
	 * @param \WC_Product                $product          The product or variation to sync.
	 * @param \WC_Bsale\Bsale\API_Client $bsale_api_client The Bsale API client object.
	 *
	 * @return bool True if the price was updated, false otherwise.
	 */
	private function process_price_sync( int $price_list_id, \WC_Product $product, API_Client $bsale_api_client ): bool {
		try {
			$bsale_price = $bsale_api_client->get_variant_price_from_price_list( $price_list_id, $product->get_sku() );
		} catch ( \Exception $e ) {
			$this->notify_observers_for_cron( 'price_update', $product->get_sku(), $e->getMessage(), 'error' );

			return false;
		}

		if ( ! $bsale_price ) {
			$this->notify_observers_for_cron( 'price_update', $product->get_sku(), __( 'No price found in Bsale', 'wc-bsale' ), 'warning' );

			return false;
		}

		$current_price = (float) $product->get_price( 'edit' );

		// Check if the price of the product is different from the one in Bsale. If so, update the WooCommerce price
		if ( $current_price != $bsale_price ) {
			$product->set_regular_price( $bsale_price );
			$product->save();

			$this->notify_observers_for_cron( 'price_update', $product->get_sku(), sprintf( __( 'Price updated from %s to %s', 'wc-bsale' ), $current_price, $bsale_price ), 'success' );

			return true;
		}

		return false;
	}
}