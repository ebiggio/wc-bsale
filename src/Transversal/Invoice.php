<?php
/**
 * Handles the invoice hook generation and the invoice generation itself.
 *
 * @package WC_Bsale
 * @class   Invoice
 */

namespace WC_Bsale\Transversal;

defined( 'ABSPATH' ) || exit;

use WC_Bsale\Bsale\API_Client;
use WC_Bsale\DB_Logger;
use WC_Bsale\Interfaces\API_Consumer;
use WC_Bsale\Interfaces\Observer;

/**
 * Invoice class
 */
class Invoice implements API_Consumer {
	/**
	 * The observers that will be notified when an event is triggered.
	 *
	 * @see Observer
	 *
	 * @var array
	 */
	private array $observers = array();
	/**
	 * The invoice settings, loaded from the Invoice class in the admin settings.
	 *
	 * @see \WC_Bsale\Admin\Settings\Invoice Invoice settings class
	 *
	 * @var array
	 */
	private array $settings;

	public function __construct() {
		// Add the database logger as an observer
		$this->add_observer( DB_Logger::get_instance() );

		// Get the invoice settings
		$this->settings = \WC_Bsale\Admin\Settings\Invoice::get_instance()->get_settings();

		$this->register_invoice_hooks();
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
	 * Sets the hook for invoice generation to the order status change configured in the plugin options.
	 *
	 * @return void
	 */
	private function register_invoice_hooks(): void {
		// Check if the invoice generation is enabled. If not, we don't need to do anything
		if ( ! $this->settings['enabled'] ) {
			return;
		}

		// Add the action to generate the invoice
		add_action( 'woocommerce_order_status_' . substr( $this->settings['order_status'], 3 ), array( $this, 'generate_invoice' ), 10, 2 );
	}

	/**
	 * Generates an invoice in Bsale according to the order details of the given order ID.
	 *
	 * Won't generate an invoice if the order has already been invoiced (checked by the '_wc_bsale_invoice_details' meta data).
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return void
	 * @throws \Exception If there is an error getting the tax data from Bsale.
	 */
	public function generate_invoice( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Check if the order has already been invoiced
		$bsale_invoice_details = get_post_meta( $order_id, '_wc_bsale_invoice_details', true );

		if ( $bsale_invoice_details ) {
			$this->notify_observers( 'generate_invoice', 'invoice', $order_id, __( 'The order has already been invoiced in Bsale' ) );

			return;
		}

		// Get the tax information from Bsale. This is needed to calculate the net unit value of the products
		$tax_id = (int) $this->settings['tax_id'];

		$bsale_api = new API_Client();

		try {
			$tax_data = $bsale_api->get_tax_by_id( $tax_id );
		} catch ( \Exception $e ) {
			$this->notify_observers( 'generate_invoice', 'invoice', $order_id, __( 'Error getting the tax data from Bsale: )' . $e->getMessage() ), 'error' );

			return;
		}

		if ( ! $tax_data ) {
			// We need the tax data to calculate the net unit value. Without it, we can't generate the invoice
			$this->notify_observers( 'generate_invoice', 'invoice', $order_id, __( 'Error getting the tax data from Bsale: The tax data was not found' ), 'error' );

			return;
		}

		// Get the shipping cost and calculate its value without the tax
		$shipping_cost = $order->get_shipping_total();
		if ( $shipping_cost > 0 ) {
			$shipping_cost = $shipping_cost / ( 1 + ( $tax_data['percentage'] / 100 ) );
		}

		if ( $this->settings['send_email'] && $order->get_billing_email() ) {
			// Send the invoice to the customer's email
			$customer_email      = $order->get_billing_email();
			$customer_first_name = trim( $order->get_billing_first_name() );
			$customer_last_name  = trim( $order->get_billing_last_name() );

			// We need to check if the customer's first name is set, as it is a required field in Bsale if we want to send the invoice by email
			if ( empty( $customer_first_name ) ) {
				$customer_first_name = __( 'Customer', 'wc-bsale' );
				$customer_last_name  = __( '', 'wc-bsale' );
			}

			$client_data['client'] = array(
				'firstName' => $customer_first_name,
				'lastName'  => $customer_last_name,
				'email'     => $customer_email
			);

			$client_data['sendEmail'] = 1;
		}

		// Prepare the items to be sent to Bsale
		$invoice_details = array();

		foreach ( $order->get_items() as $item ) {
			if ( $item->get_variation_id() > 0 ) {
				$product = wc_get_product( $item->get_variation_id() );
			} else {
				$product = wc_get_product( $item->get_product_id() );
			}

			// Get the price paid by the customer for a single unit of the product
			$product_price_paid = $item->get_total() / $item->get_quantity();

			// Calculate the net unit value (price without the configured tax)
			$net_unit_value = $product_price_paid / ( 1 + ( $tax_data['percentage'] / 100 ) );

			$invoice_details[] = array(
				'identifier'   => $product->get_sku(),
				'netUnitValue' => round( $net_unit_value, 2 ),
				'quantity'     => $item->get_quantity()
			);
		}

		// Add the shipping cost as a product in the invoice
		if ( $shipping_cost > 0 ) {
			$invoice_details[] = array(
				'comment'      => __( 'Shipping cost', 'wc-bsale' ),
				'netUnitValue' => round( $shipping_cost, 2 ),
				'quantity'     => 1,
				// We must send the tax ID as an array, even if it's just one tax
				'taxId'        => array( $tax_id )
			);
		}

		// Emission and expiration date must be a UNIX timestamp of the current date, with the time set to 00:00:00
		$wordpress_timezone = get_option( 'timezone_string' ) ?: 'UTC';
		$current_date       = new \DateTime( 'now', new \DateTimeZone( $wordpress_timezone ) );
		$current_date->setTime( 0, 0 );

		$invoice_data = array(
			'documentTypeId' => (int) $this->settings['document_type'],
			'officeId'       => (int) $this->settings['office_id'],
			'emissionDate'   => $current_date->getTimestamp(),
			'expirationDate' => $current_date->getTimestamp(),
			'declareSii'     => (int) $this->settings['declare_sii'],
			'priceListId'    => (int) $this->settings['price_list_id'],
			'details'        => $invoice_details,
		);

		if ( isset( $client_data ) ) {
			$invoice_data = array_merge( $invoice_data, $client_data );
		}

		$bsale_invoice_details = $bsale_api->generate_invoice( $invoice_data );

		if ( $bsale_invoice_details ) {
			// Save the invoice details in the order meta data, adding the timestamp of the invoice generation
			$bsale_invoice_details['wc_bsale_generated_at'] = current_time( 'U' );

			update_post_meta( $order_id, '_wc_bsale_invoice_details', $bsale_invoice_details );

			$this->notify_observers( 'generate_invoice', 'invoice', $order_id, __( 'Invoice successfully generated in Bsale' ), 'success' );
		} else {
			$bsale_error = $bsale_api->get_last_wp_error();

			$this->notify_observers( 'generate_invoice', 'invoice', $order_id, __( 'Error generating the invoice in Bsale: ' . $bsale_error->get_error_message() ), 'error' );
		}
	}
}