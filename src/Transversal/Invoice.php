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
	 * @var array
	 */
	private array $observers = array();
	/**
	 * The invoice settings from the plugin options.
	 *
	 * @var array
	 */
	private array $settings;

	public function __construct( array $settings ) {
		// Add the database logger as an observer
		$this->add_observer( DB_Logger::get_instance() );

		$this->settings = $settings;

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

		// TODO Implement the capability to send the invoice to the customer's email by the "sendEmail" attribute in Bsale. Will require a new setting in the plugin and a "client" node in the invoice data with at least the customer first name and email
		// Get the customer's email
		$customer_email = $order->get_billing_email();

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
				'netUnitValue' => round( $net_unit_value ),
				'quantity'     => $item->get_quantity()
			);
		}

		$invoice_data = array(
			'documentTypeId' => (int) $this->settings['document_type'],
			'officeId'       => (int) $this->settings['office_id'],
			'emissionDate'   => time(),
			'expirationDate' => time(),
			'declareSii'     => (int) $this->settings['declare_sii'],
			'priceListId'    => (int) $this->settings['price_list_id'],
			'details'        => $invoice_details,
		);

		$bsale_invoice_details = $bsale_api->generate_invoice( $invoice_data );

		if ( $bsale_invoice_details ) {
			// Save the invoice details in the order meta data
			update_post_meta( $order_id, '_wc_bsale_invoice_details', $bsale_invoice_details );

			$this->notify_observers( 'generate_invoice', 'invoice', $order_id, __( 'Invoice successfully generated in Bsale' ), 'success' );
		} else {
			$bsale_error = $bsale_api->get_last_wp_error();

			$this->notify_observers( 'generate_invoice', 'invoice', $order_id, __( 'Error generating the invoice in Bsale: ' . $bsale_error->get_error_message() ), 'error' );
		}
	}
}