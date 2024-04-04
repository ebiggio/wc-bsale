<?php
/**
 * Meta box for the Bsale invoice details.
 *
 * @package WC_Bsale
 * @class   Invoice
 */

namespace WC_Bsale\Admin\Meta_Boxes;

use DateTimeZone;
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
 */
class Invoice {
	/**
	 * The invoice settings, loaded from the Invoice class in the admin settings.
	 *
	 * @see \WC_Bsale\Admin\Settings\Invoice Invoice settings class
	 * @var array
	 */
	private array $settings;

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		$this->settings = \WC_Bsale\Admin\Settings\Invoice::get_instance()->get_settings();
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
				$generated_date->setTimezone( new DateTimeZone( $timezone_string ) );
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
					<p>
						<?php _e( 'The invoice will be automatically generated when the order status changes to ', 'wc-bsale' ); ?>
						<strong><?php echo esc_html( $configured_order_status_name ); ?></strong>.
					</p>
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