<?php
/**
 * Hooks for when viewing the list of orders in the admin.
 *
 * @package WC_Bsale
 * @class   Order_List
 */

namespace WC_Bsale\Admin\Hooks;

defined( 'ABSPATH' ) || exit;

use const WC_Bsale\PLUGIN_URL;
use const WC_Bsale\PLUGIN_VERSION;

/**
 * Order_List class
 */
class Order_List {
	/**
	 * The invoice settings, loaded from the Invoice class in the admin settings.
	 *
	 * @see \WC_Bsale\Admin\Settings\Invoice Invoice settings class.
	 * @var array
	 */
	private array $settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings = \WC_Bsale\Admin\Settings\Invoice::get_settings();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Check if the column should be displayed
		if ( $this->settings['display']['order_list_add_column'] ) {
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_invoice_column' ) );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'display_invoice_column' ), 10, 2 );
		}
	}

	/**
	 * Enqueues the assets for the order list.
	 *
	 * @param string $hook_suffix The current page hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		global $post_type;
		if ( 'edit.php' === $hook_suffix && 'shop_order' === $post_type ) {
			wp_enqueue_style( 'wc-bsale-admin', PLUGIN_URL . 'assets/css/wc-bsale.css', array(), PLUGIN_VERSION );
		}
	}

	/**
	 * Adds the Bsale invoice status column to the list of orders in the admin.
	 *
	 * @param array $columns The columns in the list of orders.
	 *
	 * @return array The columns in the list of orders with the Bsale invoice status column added.
	 */
	public function add_invoice_column( array $columns ): array {
		$order_columns = array();

		foreach ( $columns as $key => $value ) {
			$order_columns[ $key ] = $value;

			// Add the Bsale invoice column status after the order status column
			if ( 'order_status' === $key ) {
				$order_columns['wc_bsale_invoice'] = esc_html__( 'Bsale invoice status', 'wc-bsale' );
			}
		}

		return $order_columns;
	}

	/**
	 * Displays the Bsale invoice status column in the list of orders in the admin.
	 *
	 * This column will display the text "Generated" if the invoice has been generated in Bsale for that order.
	 *
	 * @param string $column  The column to display.
	 * @param int    $post_id The ID of the order.
	 */
	public function display_invoice_column( string $column, int $post_id ): void {
		if ( 'wc_bsale_invoice' === $column ) {
			$invoice_details = get_post_meta( $post_id, '_wc_bsale_invoice_details', true );

			if ( $invoice_details ) {
				echo '<span class="wc_bsale_invoice_status">' . esc_html__( 'Generated', 'wc-bsale' ) . '</span>';
			}
		}
	}
}