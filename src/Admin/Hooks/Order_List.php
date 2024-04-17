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

		// Check if the filter should be included
		if ( $this->settings['display']['order_list_include_filter'] ) {
			add_action( 'restrict_manage_posts', array( $this, 'include_invoice_filter' ) );
			add_action( 'pre_get_posts', array( $this, 'filter_orders_by_invoice_status' ) );
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

	/**
	 * Adds a filter to the list of orders to filter by Bsale invoice status.
	 *
	 * This filter will allow a user to filter orders by whether an invoice has been generated in Bsale or not.
	 *
	 * @return void
	 */
	public function include_invoice_filter(): void {
		global $typenow;

		if ( 'shop_order' === $typenow ) {
			$selected = $_GET['bsale_invoice_status'] ?? '';

			?>
			<select name="bsale_invoice_status">
				<option value=""><?php esc_html_e( 'All Bsale invoice statuses', 'wc-bsale' ); ?></option>
				<option value="generated" <?php selected( 'generated', $selected ); ?>><?php esc_html_e( 'Invoice generated', 'wc-bsale' ); ?></option>
				<option value="not_generated" <?php selected( 'not_generated', $selected ); ?>><?php esc_html_e( 'Invoice not generated', 'wc-bsale' ); ?></option>
			</select>
			<?php
		}
	}

	/**
	 * Filter orders by invoice status
	 *
	 * @param \WP_Query $query The WP_Query instance (passed by reference).
	 *
	 * @return void
	 */
	public function filter_orders_by_invoice_status( \WP_Query $query ): void {
		global $typenow, $pagenow;

		// Only apply to WooCommerce orders list in the admin
		if ( 'shop_order' !== $typenow || 'edit.php' !== $pagenow || ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Check if the filter is set
		$invoice_status_filter = sanitize_text_field( $_GET['bsale_invoice_status'] ?? '' );

		if ( $invoice_status_filter ) {
			$qv         = &$query->query_vars;
			$meta_query = $qv['meta_query'] ?? array();

			// If the filter is set to "generated", only show orders with an invoice generated in Bsale
			if ( 'generated' === $invoice_status_filter ) {
				$meta_query[] = array(
					'key'     => '_wc_bsale_invoice_details',
					'compare' => 'EXISTS',
				);
			} elseif ( 'not_generated' === $invoice_status_filter ) {
				// If the filter is set to "not_generated", only show orders without an invoice generated in Bsale
				$meta_query[] = array(
					'key'     => '_wc_bsale_invoice_details',
					'compare' => 'NOT EXISTS',
				);
			}

			$qv['meta_query'] = $meta_query;
		}
	}
}