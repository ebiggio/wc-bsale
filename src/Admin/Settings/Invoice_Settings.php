<?php
/**
 * Invoice settings for the plugin.
 *
 * @class   Invoice_Settings
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use WC_Bsale\Interfaces\Setting as Setting_Interface;

/**
 * Class Invoice_Settings
 */
class Invoice_Settings implements Setting_Interface {
	private array|bool $settings = false;
	private array|null $selected_office = null;
	private array|null $selected_price_list = null;

	public function __construct() {
		$this->settings = maybe_unserialize( get_option( 'wc_bsale_invoice' ) );

		// Default settings
		if ( ! $this->settings ) {
			$this->settings = array(
				'enabled'       => 0,
				'document_type' => 0,
				'order_status'  => 'wc-completed',
				'office_id'     => 0,
				'price_list_id' => 0,
				'declare_sii'   => 0
			);
		}
	}

	/**
	 * Validates data from the invoice settings form.
	 *
	 * @return array The validated settings to be stored in the database.
	 */
	public function validate_settings(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_messages', __( 'You do not have sufficient permissions to access this page.' ) );

			return array();
		}

		$order_statuses = wc_get_order_statuses();

		$selected_order_status = isset( $_POST['wc_bsale_invoice']['order_status'] ) ? sanitize_text_field( $_POST['wc_bsale_invoice']['order_status'] ) : 'wc-completed';

		$settings['enabled']       = isset( $_POST['wc_bsale_invoice']['enabled'] ) ? 1 : 0;
		$settings['document_type'] = (int) $_POST['wc_bsale_invoice']['document_type'];
		$settings['order_status']  = array_key_exists( $selected_order_status, $order_statuses ) ? $selected_order_status : 'wc-completed';
		$settings['office_id']     = (int) $_POST['wc_bsale_invoice']['office_id'];
		$settings['price_list_id'] = (int) $_POST['wc_bsale_invoice']['price_list_id'];
		$settings['declare_sii']   = isset( $_POST['wc_bsale_invoice']['declare_sii'] ) ? 1 : 0;

		return $settings;
	}

	/**
	 * Returns the title of the settings page.
	 *
	 * @return string
	 */
	public function get_setting_title(): string {
		return __( 'Invoice settings', 'wc-bsale' );
	}

	/**
	 * Displays the invoice settings page.
	 *
	 * @return void
	 */
	public function display_settings(): void {
		add_settings_section(
			'wc_bsale_invoice_section',
			__( 'Invoice settings', 'wc-bsale' ),
			array( $this, 'section_description' ),
			'wc_bsale_invoice'
		);

		add_settings_field(
			'wc_bsale_invoice_enable',
			__( 'Generate invoices?', 'wc-bsale' ),
			array( $this, 'enable_callback' ),
			'wc_bsale_invoice',
			'wc_bsale_invoice_section'
		);

		add_settings_field(
			'wc_bsale_document_type',
			__( 'Document type for the invoice', 'wc-bsale' ),
			array( $this, 'document_type_callback' ),
			'wc_bsale_invoice',
			'wc_bsale_invoice_section'
		);

		add_settings_field(
			'wc_bsale_invoice_order_status',
			__( 'Generate invoice when the order status changes to', 'wc-bsale' ),
			array( $this, 'order_status_callback' ),
			'wc_bsale_invoice',
			'wc_bsale_invoice_section'
		);

		add_settings_field(
			'wc_bsale_invoice_office',
			__( 'Office where the document is generated', 'wc-bsale' ),
			array( $this, 'office_callback' ),
			'wc_bsale_invoice',
			'wc_bsale_invoice_section'
		);

		add_settings_field(
			'wc_bsale_price_list',
			__( 'Price list to use with the invoice', 'wc-bsale' ),
			array( $this, 'price_list_callback' ),
			'wc_bsale_invoice',
			'wc_bsale_invoice_section'
		);

		add_settings_field(
			'wc_bsale_declare_sii',
			__( 'Declare invoice?', 'wc-bsale' ),
			array( $this, 'declare_sii_callback' ),
			'wc_bsale_invoice',
			'wc_bsale_invoice_section'
		);

		settings_fields( 'wc_bsale_invoice_settings_group' );
		do_settings_sections( 'wc_bsale_invoice' );
	}

	/**
	 * Callback for the invoice section description.
	 *
	 * @return void
	 */
	public function section_description(): void {
		?>
		<hr><p><?php esc_html_e( 'Settings to configure the invoice capabilities of the plugin.', 'wc-bsale' ); ?></p>
		<?php
	}

	/**
	 * Callback for the enable invoice generation field.
	 *
	 * @return void
	 */
	public function enable_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Enable invoice generation', 'wc-bsale' ); ?></span></legend>
			<label for="wc_bsale_invoice_enabled">
				<input type="checkbox" name="wc_bsale_invoice[enabled]" id="wc_bsale_invoice_enabled" value="1" <?php checked( $this->settings['enabled'] ?? false ); ?>>
				<?php esc_html_e( 'Enable invoice generation', 'wc-bsale' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Enable or disable the generation of invoices for orders.', 'wc-bsale' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the document type field.
	 *
	 * @return void
	 */
	public function document_type_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Document type for the invoice', 'wc-bsale' ); ?></span></legend>
			<select name="wc_bsale_invoice[document_type]" id="wc_bsale_invoice_document_type">
			</select>
			<p class="description"><?php esc_html_e( 'Select the type of document that represent an electronic invoice in Bsale.', 'wc-bsale' ); ?></p>
			<div class="wc-bsale-notice wc-bsale-notice-error">
				<p>
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'If you select a document type that is not an electronic invoice, the document will not be generated.', 'wc-bsale' ); ?>
				</p>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the order status field.
	 *
	 * @return void
	 */
	public function order_status_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Generate invoice on order status', 'wc-bsale' ); ?></span></legend>
			<select name="wc_bsale_invoice[order_status]" id="wc_bsale_invoice_order_status">
				<?php foreach ( wc_get_order_statuses() as $status => $label ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $this->settings['order_status'] ?? '', $status ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Select the order status that will trigger the generation of the invoice.', 'wc-bsale' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the office field.
	 *
	 * @return void
	 */
	public function office_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Office where the document is generated', 'wc-bsale' ); ?></span></legend>
			<select id="wc_bsale_invoice_office_id" name="wc_bsale_invoice[office_id]">
				<option value=""><?php esc_html_e( 'Use the default office', 'wc-bsale' ); ?></option>
				<?php
				if ( $this->selected_office ) {
					echo '<option value="' . $this->selected_office['id'] . '" selected>' . $this->selected_office['text'] . '</option>';
				}
				?>
			</select>
			<p class="description"><?php esc_html_e( 'Select the office where the document is generated. If no office is selected, the default office on Bsale for your account will be used.', 'wc-bsale' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the price list field.
	 *
	 * @return void
	 */
	public function price_list_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Price list to use with the invoice', 'wc-bsale' ); ?></span></legend>
			<select id="wc_bsale_price_list_id" name="wc_bsale_invoice[price_list_id]">
				<option value=""><?php esc_html_e( 'Use the default price list', 'wc-bsale' ); ?></option>
				<?php
				if ( $this->selected_price_list ) {
					echo '<option value="' . $this->selected_price_list['id'] . '" selected>' . $this->selected_price_list['text'] . '</option>';
				}
				?>
			</select>
			<p class="description"><?php esc_html_e( 'Select the price list to use with the invoice. If no price list is selected, the default price list of the selected office will be used.', 'wc-bsale' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the SII field.
	 *
	 * @return void
	 */
	public function declare_sii_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Declare document?', 'wc-bsale' ); ?></span></legend>
			<label for="wc_bsale_declare_sii">
				<input type="checkbox" name="wc_bsale_invoice[declare_sii]" id="wc_bsale_declare_sii" value="1" <?php checked( $this->settings['declare_sii'] ?? false ); ?>>
				<?php esc_html_e( 'Declare the invoice to the SII', 'wc-bsale' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'If you wish to declare the document to the SII, check this option.', 'wc-bsale' ); ?></p>
		</fieldset>
		<?php
	}
}
