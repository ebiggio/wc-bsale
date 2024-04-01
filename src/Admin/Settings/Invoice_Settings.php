<?php
/**
 * Invoice settings for the plugin.
 *
 * @class   Invoice_Settings
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use WC_Bsale\Bsale\API_Client;
use WC_Bsale\Interfaces\Setting as Setting_Interface;
use const WC_Bsale\PLUGIN_URL;

/**
 * Class Invoice_Settings
 */
class Invoice_Settings implements Setting_Interface {
	private array|bool $settings = false;
	private array|null $selected_document_type = null;
	private array|null $selected_office = null;
	private array|null $selected_price_list = null;
	private array|null $selected_tax = null;

	public function __construct() {
		$this->settings = maybe_unserialize( get_option( 'wc_bsale_invoice' ) );

		// Default settings
		if ( ! $this->settings ) {
			$this->settings = array(
				'enabled'       => 0,
				'order_status'  => 'wc-completed',
				'document_type' => 0,
				'office_id'     => 0,
				'price_list_id' => 0,
				'tax_id'        => 0,
				'declare_sii'   => 0,
				'send_email'    => 0
			);
		}
	}

	/**
	 * Returns an instance of the class.
	 *
	 * @return self The instance of the class.
	 */
	public static function get_instance(): self {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Returns the settings stored in the database. If the settings are not found, a set of default settings are returned.
	 *
	 * @return array The invoice settings.
	 */
	public function get_settings(): array {
		return $this->settings;
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
		$settings['order_status']  = array_key_exists( $selected_order_status, $order_statuses ) ? $selected_order_status : 'wc-completed';
		$settings['document_type'] = (int) isset( $_POST['wc_bsale_invoice']['document_type'] ) ? $_POST['wc_bsale_invoice']['document_type'] : 0;
		$settings['office_id']     = (int) isset( $_POST['wc_bsale_invoice']['office_id'] ) ? $_POST['wc_bsale_invoice']['office_id'] : 0;
		$settings['price_list_id'] = (int) isset( $_POST['wc_bsale_invoice']['price_list_id'] ) ? $_POST['wc_bsale_invoice']['price_list_id'] : 0;
		$settings['tax_id']        = (int) isset( $_POST['wc_bsale_invoice']['tax_id'] ) ? $_POST['wc_bsale_invoice']['tax_id'] : 0;
		$settings['declare_sii']   = isset( $_POST['wc_bsale_invoice']['declare_sii'] ) ? 1 : 0;
		$settings['send_email']    = isset( $_POST['wc_bsale_invoice']['send_email'] ) ? 1 : 0;

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
	 * Loads the resources needed for the invoice settings page (styles and scripts).
	 *
	 * @return void
	 */
	private function load_page_resources(): void {
		// Enqueue WooCommerce's admin styles and the product editor styles for Select2
		wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css' );
		wp_enqueue_style( 'woocommerce_product_editor_styles', WC()->plugin_url() . '/assets/client/admin/product-editor/style.css' );

		// WooCommerce JS script for Select2
		wp_enqueue_script( 'wc-enhanced-select' );

		// Enqueue the JavaScript file for the office selection and localize the script with the URL for the AJAX request
		wp_enqueue_script( 'wc-bsale-admin-invoice', PLUGIN_URL . 'assets/js/wc-bsale-admin-invoice.js', array( 'jquery' ), null, true );
		wp_localize_script( 'wc-bsale-admin-invoice', 'invoice_parameters', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'search_bsale_data' )
		) );
	}

	/**
	 * Loads a Bsale entity from the API.
	 *
	 * @param int    $entity_id  The ID of the entity to load.
	 * @param string $api_method The method to call in the Bsale API client.
	 *
	 * @return array The entity data. If the entity is not found, an empty array is returned.
	 */
	private function load_bsale_entity( int $entity_id, string $api_method ): array {
		if ( $entity_id ) {
			$bsale_api_client = new API_Client();

			try {
				$entity = $bsale_api_client->$api_method( $entity_id );
			} catch ( \Exception ) {
				$entity = null;
			}

			if ( $entity ) {
				return array(
					'id'   => $entity['id'],
					'text' => $entity['name']
				);
			}
		}

		return array();
	}

	/**
	 * Gets the document type data from Bsale for the document type ID stored in the settings.
	 *
	 * @return void
	 */
	private function load_bsale_document_type(): void {
		$this->selected_document_type = $this->load_bsale_entity( (int) $this->settings['document_type'], 'get_document_type_by_id' );
	}

	/**
	 * Gets the office data from Bsale for the office ID stored in the settings.
	 *
	 * @return void
	 */
	private function load_bsale_office(): void {
		$this->selected_office = $this->load_bsale_entity( (int) $this->settings['office_id'], 'get_office_by_id' );
	}

	/**
	 * Gets the price list data from Bsale for the price list ID stored in the settings.
	 *
	 * @return void
	 */
	private function load_bsale_price_list(): void {
		$this->selected_price_list = $this->load_bsale_entity( (int) $this->settings['price_list_id'], 'get_price_list_by_id' );
	}

	/**
	 * Gets the tax data from Bsale for the tax ID stored in the settings.
	 *
	 * @return void
	 */
	private function load_bsale_tax(): void {
		$this->selected_tax = $this->load_bsale_entity( (int) $this->settings['tax_id'], 'get_tax_by_id' );
	}

	/**
	 * Displays the invoice settings page.
	 *
	 * @return void
	 */
	public function display_settings(): void {
		$this->load_page_resources();
		$this->load_bsale_document_type();
		$this->load_bsale_office();
		$this->load_bsale_price_list();
		$this->load_bsale_tax();

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
			'wc_bsale_invoice_order_status',
			__( 'Generate invoice when the order status changes to', 'wc-bsale' ),
			array( $this, 'order_status_callback' ),
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
			'wc_bsale_tax',
			__( 'Bsale tax to use with the invoice', 'wc-bsale' ),
			array( $this, 'tax_callback' ),
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

		add_settings_field(
			'wc_bsale_send_email',
			__( 'Instruct Bsale to send the invoice by email?', 'wc-bsale' ),
			array( $this, 'send_email_callback' ),
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
	 * Callback for the order status field.
	 *
	 * @return void
	 */
	public function order_status_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Generate invoice on order status', 'wc-bsale' ); ?></span></legend>
			<select name="wc_bsale_invoice[order_status]" id="wc_bsale_invoice_order_status" style="width: 50%">
				<?php foreach ( wc_get_order_statuses() as $status => $label ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $this->settings['order_status'] ?? '', $status ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Select the order status that will trigger the generation of the invoice.', 'wc-bsale' ); ?></p>
			<div class="wc-bsale-notice wc-bsale-notice-success">
				<p>
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Even if an order changes status to the selected one multiple times, the invoice will only be generated once. The plugin will check if the order has already been invoiced, and if so, it will not generate a new invoice.', 'wc-bsale' ); ?>
				</p>
			</div>
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
			<select name="wc_bsale_invoice[document_type]" id="wc_bsale_invoice_document_type" class="wc-bsale-ajax-select2" data-placeholder="<?php esc_attr_e( 'Search a document type by name', 'wc-bsale' ); ?>"
					data-ajax-action="search_bsale_document_types" style="width: 50%">
				<?php
				if ( $this->selected_document_type ) {
					echo '<option value="' . $this->selected_document_type['id'] . '" selected>' . $this->selected_document_type['text'] . '</option>';
				}
				?>
			</select>
			<p class="description"><?php esc_html_e( 'Select the type of document that represent an electronic invoice in Bsale.', 'wc-bsale' ); ?></p>
			<div class="wc-bsale-notice wc-bsale-notice-error">
				<p>
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'If you select a document type that is not an electronic invoice, the document won\'t be generated or will be created with errors.', 'wc-bsale' ); ?>
				</p>
			</div>
			<div class="wc-bsale-notice wc-bsale-notice-info">
				<p>
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'If you don\'t see the document type you are looking for, please make sure it is active and its name is not empty. Only electronic invoice document types will be shown.', 'wc-bsale' ); ?>
				</p>
			</div>
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
			<select id="wc_bsale_invoice_office_id" name="wc_bsale_invoice[office_id]" class="wc-bsale-ajax-select2" data-placeholder="<?php esc_attr_e( 'Search an office by name', 'wc-bsale' ); ?>" data-allow-clear="true"
					data-ajax-action="search_bsale_offices" style="width: 50%">
				<?php
				if ( $this->selected_office ) {
					echo '<option value="' . $this->selected_office['id'] . '" selected>' . $this->selected_office['text'] . '</option>';
				}
				?>
			</select>
			<p class="description"><?php esc_html_e( 'Select the Bsale office where the document is generated. If no office is selected, the default office of your Bsale account will be used.', 'wc-bsale' ); ?></p>
			<div class="wc-bsale-notice wc-bsale-notice-info">
				<p>
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'If you don\'t see the office you are looking for, please make sure it is active and its name is not empty.', 'wc-bsale' ); ?>
				</p>
			</div>
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
			<select id="wc_bsale_price_list_id" name="wc_bsale_invoice[price_list_id]" class="wc-bsale-ajax-select2" data-placeholder="<?php esc_attr_e( 'Search a price list by name', 'wc-bsale' ); ?>" data-allow-clear="true"
					data-ajax-action="search_bsale_price_lists" style="width: 50%">
				<?php
				if ( $this->selected_price_list ) {
					echo '<option value="' . $this->selected_price_list['id'] . '" selected>' . $this->selected_price_list['text'] . '</option>';
				}
				?>
			</select>
			<p class="description"><?php esc_html_e( 'Select the price list to use with the invoice. If no price list is selected, the default price list of the selected office will be used.', 'wc-bsale' ); ?></p>
			<div class="wc-bsale-notice wc-bsale-notice-info">
				<p>
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'If you don\'t see the price list you are looking for, please make sure it is active and its name is not empty.', 'wc-bsale' ); ?>
				</p>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the tax field.
	 *
	 * @return void
	 */
	public function tax_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Bsale tax to use with the invoice', 'wc-bsale' ); ?></span></legend>
			<select name="wc_bsale_invoice[tax_id]" id="wc_bsale_invoice_tax_id" class="wc-bsale-ajax-select2" data-placeholder="<?php esc_attr_e( 'Search a tax by name', 'wc-bsale' ); ?>" data-allow-clear="true"
					data-ajax-action="search_bsale_taxes" style="width: 50%">
				<?php
				if ( $this->selected_tax ) {
					echo '<option value="' . $this->selected_tax['id'] . '" selected>' . $this->selected_tax['text'] . '</option>';
				}
				?>
			</select>
			<p class="description"><?php esc_html_e( 'This tax will be used to calculate the net price of the products. That net price will be then send to Bsale in the invoice details.', 'wc-bsale' ); ?></p>
		</fieldset>
		<div class="wc-bsale-notice wc-bsale-notice-warning">
			<p>
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'If you don\'t select a tax, the electronic invoice will be generated as an exempt invoice.', 'wc-bsale' ); ?>
			</p>
		</div>
		<div class="wc-bsale-notice wc-bsale-notice-info">
			<p>
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'If you don\'t see the tax you are looking for, please make sure it is active and its name is not empty.', 'wc-bsale' ); ?>
			</p>
		</div>
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

	/**
	 * Callback for the send email field.
	 *
	 * @return void
	 */
	public function send_email_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Instruct Bsale to send the invoice by email?', 'wc-bsale' ); ?></span></legend>
			<label for="wc_bsale_send_email">
				<input type="checkbox" name="wc_bsale_invoice[send_email]" id="wc_bsale_send_email" value="1" <?php checked( $this->settings['send_email'] ?? false ); ?>>
				<?php esc_html_e( 'Send the invoice to the customer\'s email', 'wc-bsale' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Check this option if you would like instruct Bsale to send the invoice to the customer by email. For this, the customer\'s firstname and email will be sent in the invoice data.', 'wc-bsale' ); ?></p>
		</fieldset>
		<?php
	}
}
