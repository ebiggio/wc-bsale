<?php
/**
 * Meta box for the Bsale invoice details.
 *
 * @package WC_Bsale
 * @class   Invoice
 */

namespace WC_Bsale\Admin\Meta_Boxes;

use const WC_Bsale\PLUGIN_URL;
use const WC_Bsale\PLUGIN_VERSION;

defined( 'ABSPATH' ) || exit;

/**
 * Invoice class
 *
 * Manages the invoice meta box in the shop_order post type.
 * This meta box displays the following information if the invoice has been successfully generated:
 * - The invoice number.
 * - A link to view the invoice in Bsale (provided by the Bsale, as a PDF).
 * - The total amount of the invoice.
 *
 * If the invoice has not been generated yet and the order status is the one configured in the plugin settings, it will display a button to generate the invoice.
 */
class Invoice {
	/**
	 * The invoice settings, loaded from the Invoice class in the admin settings.
	 *
	 * @see \WC_Bsale\Admin\Settings\Invoice Invoice settings class.
	 * @var array
	 */
	private array $settings;

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		$this->settings = \WC_Bsale\Admin\Settings\Invoice::get_settings();

		// Check for the success transient to show a success message if an invoice was generated successfully
		if ( get_transient( 'wc_bsale_invoice_success' ) ) {
			add_action( 'admin_notices', function () {
				?>
				<div class="notice notice-success">
					<p><?php _e( 'The invoice has been successfully generated in Bsale.', 'wc-bsale' ); ?></p>
				</div>
				<?php
			} );

			delete_transient( 'wc_bsale_invoice_success' );
		}

		// Check for the error transient to show an error message if there was an error generating an invoice
		$error_message = get_transient( 'wc_bsale_invoice_error' );
		if ( $error_message ) {
			add_action( 'admin_notices', function () use ( $error_message ) {
				?>
				<div class="notice notice-error">
					<p><?php _e( 'There was an error generating the invoice:', 'wc-bsale' ); ?></p>
					<p><strong><?php echo esc_html( $error_message ); ?></strong></p>
				</div>
				<?php
			} );

			delete_transient( 'wc_bsale_invoice_error' );
		}
	}

	/**
	 * Enqueues the assets for the Bsale invoice meta box.
	 *
	 * @param string $hook_suffix The current page hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		global $post_type;
		if ( 'post.php' === $hook_suffix && 'shop_order' === $post_type ) {
			wp_enqueue_style( 'wc-bsale-admin', PLUGIN_URL . 'assets/css/wc-bsale.css', array(), PLUGIN_VERSION );

			// Add the Thickbox script for the confirmation dialog
			add_thickbox();
		}
	}

	/**
	 * Adds the Invoice meta box to the shop_order post type.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'wc_bsale_invoice',
			__( 'Bsale Invoice', 'wc-bsale' ),
			array( $this, 'meta_box_content' ),
			'shop_order',
			'side',
			'high'
		);
	}

	/**
	 * Displays the content of the Bsale invoice meta box.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return void
	 * @throws \Exception If there's an error creating the WC DateTime object or the DateTimeZone from the timestamp of the invoice generation.
	 */
	public function meta_box_content( \WP_Post $post ): void {
		$order = wc_get_order( $post->ID );

		// Get the invoice data from the meta data
		$invoice_data = get_post_meta( $post->ID, '_wc_bsale_invoice_details', true );

		// Get the order status name configured to generate the invoice
		$configured_order_status      = $this->settings['order_status'];
		$configured_order_status_name = wc_get_order_status_name( $configured_order_status );

		?>
		<div>
			<?php if ( $invoice_data ) :
				$timezone_string = get_option( 'timezone_string' ) ?: 'UTC';
				$generated_date = new \WC_DateTime();
				$generated_date->setTimestamp( $invoice_data['wc_bsale_generated_at'] );
				$generated_date->setTimezone( new \DateTimeZone( $timezone_string ) );
				?>
				<div class="wc-bsale-notice wc-bsale-notice-success">
					<p>
						<strong>
							<?php _e( 'Invoice generated successfully on ', 'wc-bsale' ); ?>
							<?php echo esc_html( wc_format_datetime( $generated_date ) ); ?> @ <?php echo esc_html( $generated_date->format( wc_time_format() ) ); ?>
						</strong>
					</p>
				</div>
				<table class="wc_bsale_invoice_table">
					<tr>
						<th><?php _e( 'Invoice number', 'wc-bsale' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $invoice_data['number'] ) ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'Invoice link', 'wc-bsale' ); ?></th>
						<td><a href="<?php echo esc_url( $invoice_data['urlPdf'] ); ?>" target="_blank"><?php _e( 'View invoice', 'wc-bsale' ); ?></a> <span class="dashicons dashicons-pdf"></span></td>
					</tr>
					<tr>
						<th><?php _e( 'Total amount', 'wc-bsale' ); ?></th>
						<td><?php echo wc_price( $invoice_data['totalAmount'] ) ?></td>
					</tr>
				</table>
			<?php else : ?>
				<?php if ( $this->settings['enabled'] ) : ?>
					<div class="wc-bsale-notice wc-bsale-notice-info">
						<p>
							<?php _e( 'The invoice has not been generated yet.', 'wc-bsale' ); ?>
						</p>
					</div>
					<?php if ( $order->get_status() === substr( $configured_order_status, 3 ) ) : ?>
						<p>
							<?php _e( 'You can use the following button to generate the invoice in Bsale. ', 'wc-bsale' ); ?>
						</p>
						<p style="text-align: center">
							<input type="button" class="button button-primary" value="<?php _e( 'Generate invoice', 'wc-bsale' ); ?>"
								   onclick="tb_show('<?php _e( 'Generate invoice in Bsale?', 'wc-bsale' ); ?>', '#TB_inline?width=280&height=140&inlineId=wc_bsale_generate_invoice_confirmation');">
						</p>
						<!-- Confirmation dialog -->
						<div id="wc_bsale_generate_invoice_confirmation" style="display: none;">
							<p><?php _e( 'This action will generate the invoice in Bsale. Are you sure you want to continue?', 'wc-bsale' ); ?></p>
							<p>
								<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=wc_bsale_generate_invoice&post_id=' . $post->ID ) ); ?>&_wpnonce=<?php echo wp_create_nonce( 'wc_bsale_generate_invoice' ); ?>"
								   class="button button-primary">
									<?php _e( 'Yes, generate the invoice', 'wc-bsale' ); ?>
								</a>
								<a href="#" class="button button-secondary" onclick="tb_remove();">
									<?php _e( 'No, cancel', 'wc-bsale' ); ?>
								</a>
							</p>
						</div>
					<?php else : ?>
						<p>
							<?php _e( 'The invoice will be automatically generated when the order status changes to ', 'wc-bsale' ); ?>
							<strong><?php echo esc_html( $configured_order_status_name ); ?></strong>.
						</p>
					<?php endif; ?>
				<?php else : ?>
					<div class="wc-bsale-notice wc-bsale-notice-info">
						<p>
							<?php _e( 'The invoice generation is disabled.', 'wc-bsale' ); ?>
						</p>
					</div>
					<p>
						<?php _e( 'If you would like to generate an invoice, please enable the invoice generation in the plugin settings page.', 'wc-bsale' ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}