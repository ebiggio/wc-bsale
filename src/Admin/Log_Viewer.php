<?php
/**
 * Display the operation log page.
 */

namespace WC_BSale\Admin;

use const WC_Bsale\PLUGIN_URL;
use const WC_Bsale\PLUGIN_VERSION;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Log_Viewer class
 */
class Log_Viewer extends \WP_List_Table {
	private string $table_name;

	/**
	 * Log_Viewer constructor. Must call the parent constructor.
	 */
	public function __construct() {
		parent::__construct( [
			'singular' => 'log',
			'plural'   => 'logs',
			'ajax'     => false,
		] );

		$this->table_name = $GLOBALS['wpdb']->prefix . 'wc_bsale_operation_log';

		wp_enqueue_style( 'wc-bsale-admin', PLUGIN_URL . 'assets/css/wc-bsale.css', array(), PLUGIN_VERSION );
	}

	/**
	 * Get the log records from the database, according to the parameters.
	 *
	 * @param string $orderby  The column to order by.
	 * @param string $order    The order direction.
	 * @param int    $per_page The number of records to show per page.
	 * @param int    $offset   The offset to start the records from.
	 * @param string $search   The search string. Will be used to filter the "event_trigger", "identifier" and "message" columns.
	 *
	 * @return array|object|\stdClass[]|null The log records. If no records are found, returns null.
	 */
	private function get_logs( string $orderby, string $order, int $per_page = 20, int $offset = 0, string $search = '' ): array|object|null {
		global $wpdb;

		$sql = "SELECT * FROM $this->table_name";

		if ( $search ) {
			$sql .= $wpdb->prepare( " WHERE event_trigger LIKE %s OR identifier LIKE %s OR message LIKE %s", '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		$sql .= " ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get the total number of log records in the database, according to the search string.
	 *
	 * @param string $search The search string. Will be used to filter the "event_trigger", "identifier" and "message" columns.
	 *
	 * @return string|null The total number of log records. If no records are found, returns null.
	 */
	private function get_total_logs( string $search = '' ): ?string {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM $this->table_name";

		if ( $search ) {
			$sql .= $wpdb->prepare( " WHERE event_trigger LIKE %s OR identifier LIKE %s OR message LIKE %s", '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		return $wpdb->get_var( $sql );
	}

	/**
	 * Clears the log table.
	 *
	 * @return void
	 */
	private function clear_log(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE $this->table_name" );
	}

	/**
	 * Returns the columns for the log table.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'operation_datetime' => __( 'Date', 'wc-bsale' ),
			'event_trigger'      => __( 'Event', 'wc-bsale' ),
			'identifier'         => __( 'Identifier', 'wc-bsale' ),
			'message'            => __( 'Message', 'wc-bsale' ),
		);
	}

	/**
	 * Defines the columns that are sortable.
	 *
	 * @return array
	 */
	protected function get_sortable_columns(): array {
		return array(
			'operation_datetime' => array( 'operation_datetime', false ),
			'event_trigger'      => array( 'event_trigger', false ),
			'identifier'         => array( 'identifier', false )
		);
	}

	/**
	 * Gets the log records from the database and prepares them for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$orderby   = ! empty( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'operation_datetime';
		$order     = ! empty( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'DESC';
		$search    = ! empty( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';
		$clear_log = ! empty( $_POST['clear_log'] ) ? $_POST['clear_log'] : '';

		if ( $clear_log ) {
			$this->clear_log();
		}

		$hidden       = [];
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$this->_column_headers = [ $this->get_columns(), $hidden, $this->get_sortable_columns() ];

		$this->items = $this->get_logs( $orderby, $order, $per_page, $offset, $search );

		$total_items = $this->get_total_logs( $search );

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
		] );
	}

	/**
	 * Defines the default column output.
	 *
	 * @param object $item        The current log record.
	 * @param string $column_name The name of the column.
	 *
	 * @return string The column output.
	 */
	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'operation_datetime':
				// Return date according to the WordPress settings
				return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item[ $column_name ] ) );
			case 'message':
			case 'event_trigger':
				return $item[ $column_name ];
			case 'identifier':
				return $this->generate_admin_url( $item['event_trigger'], $item[ $column_name ] );
			default:
				return print_r( $item, true ); // Show the whole array for troubleshooting purposes
		}
	}

	/**
	 * Generate an admin URL for the identifier column, according to the event trigger.
	 *
	 * @param string $event_trigger The event trigger that caused the log entry (name of the method that triggered the event).
	 * @param string $identifier    The identifier related to the event: a product SKU or an order ID.
	 *
	 * @return string The URL to the product or order edit page. If the identifier is not found, an empty string is returned.
	 */
	private function generate_admin_url( string $event_trigger, string $identifier ): string {
		$identifier_url = '';

		switch ( $event_trigger ) {
			case 'add_to_cart':
			case 'check_cart_items_checkout':
				// The identifier is a product or variation SKU
				$product_id = wc_get_product_id_by_sku( $identifier );

				if ( ! $product_id ) {
					$identifier_url = __( 'Product SKU [' . $identifier . '] not found', 'wc-bsale' );
					break;
				}

				$product = wc_get_product( $product_id );

				if ( $product && $product->is_type( 'variation' ) ) {
					// For variations, the URL points to the parent product
					$parent_id = $product->get_parent_id();
					$edit_url  = admin_url( 'post.php?post=' . $parent_id . '&action=edit' );
					$text_link = __('Variation', 'wc-bsale');
				} else {
					// For simple products, generate the edit URL normally
					$edit_url = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
					$text_link = __('Product', 'wc-bsale');
				}

				$identifier_url = '<a href="' . $edit_url . '" target="_blank" >' . $text_link . ' SKU [' . $identifier . ']' . '</a>';
				break;
			case 'consume_bsale_stock':
				// The identifier is an order ID
				$order_id = (int) $identifier;
				$order    = wc_get_order( $order_id );

				if ( ! $order ) {
					$identifier_url = __( 'Order ID ' . $order_id . ' not found', 'wc-bsale' );
					break;
				}

				$identifier_url = '<a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank" >' . __( 'Order ID ' . $order_id, 'wc-bsale' ) . '</a>';
				break;
		}

		return $identifier_url;
	}

	/**
	 * Defines a CSS class for the log rows, according to the result code, before displaying the row.
	 *
	 * @param array $item The current log record.
	 *
	 * @return void
	 */
	public function single_row( $item ): void {
		$class = match ( $item['result_code'] ) {
			'error' => 'log-error',
			'warning' => 'log-warning',
			'success' => 'log-success',
			default => '',
		};

		echo '<tr class="' . $class . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Displays the log page content.
	 *
	 * @return void
	 */
	public function log_page_content(): void {
		$log_viewer = new Log_Viewer();
		$log_viewer->prepare_items();
		?>
		<div class="wrap">
			<h1><?php _e( 'Operations log', 'wc-bsale' ); ?></h1>
			<div>
				<p><?php _e( 'This page shows the unsupervised operations that have been performed by the plugin, such as stock updates triggered by storefront events and stock consumption triggered by order status changes. The actions that display a message to the user (such as stock updates triggered by the admin) are not logged here.', 'wc-bsale' ); ?></p>
				<div class="wc-bsale-notice wc-bsale-notice-info">
					<p><span class="dashicons dashicons-visibility"></span> The "Identifier" column contains links to the product or order that triggered the event. Click on the link to go to the product or order edit page. In the case of products or
						variations, the SKU is shown in brackets for easier identification of spaces or special characters.</p>
				</div>
			</div>
			<form method="post">
				<?php
				$log_viewer->search_box( __( 'Search', 'wc-bsale' ), 'log' );
				$log_viewer->display();
				submit_button( __( 'Clear log', 'wc-bsale' ), 'delete', 'clear_log', false );
				?>
			</form>
		</div>
		<script type="text/javascript">
            jQuery(document).ready(function ($) {
                // Remove the striped class from the table, because we don't need it and would cause a conflict with the log colors
                $('.wp-list-table').removeClass('striped');

                // Add a confirmation dialog to the clear log button
                $('input[name="clear_log"]').click(function () {
                    return confirm('<?php _e( 'Are you sure you want to clear the log? This action cannot be undone.', 'wc-bsale' ); ?>');
                });
            });
		</script>
		<?php
	}
}
