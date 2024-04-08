<?php
/**
 * Stock settings page.
 *
 * @class   Stock
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use WC_Bsale\Bsale\API_Client;
use WC_Bsale\Interfaces\Setting as Setting_Interface;
use const WC_Bsale\PLUGIN_URL;

/**
 * Stock settings class
 */
class Stock implements Setting_Interface {
	private array|bool $settings;
	/**
	 * The selected office stored in the settings, with its ID and name. Used to set the selected option in the select element. Null if no office is selected.
	 *
	 * @var array|null
	 */
	private array|null $selected_office = null;

	public function __construct() {
		$this->settings = maybe_unserialize( get_option( 'wc_bsale_stock' ) );

		// Default settings
		if ( ! $this->settings ) {
			$this->settings = array(
				'office_id'   => 0,
				'admin'       => array(
					'edit'        => 0,
					'auto_update' => 0
				),
				'storefront'  => array(
					'cart'     => 0,
					'checkout' => 0
				),
				'transversal' => array(
					'order_event'  => 'wc',
					'order_status' => array( 'wc-processing' ),
					'note'         => '{1} - Order {2}'
				)
			);
		}
	}

	/**
	 * Validates the settings of the stock settings page.
	 *
	 * @return array
	 */
	public function validate_settings(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_messages', __( 'You do not have sufficient permissions to access this page.' ) );

			return array();
		}

		// Edit product options
		$admin_settings = array(
			'edit'        => isset( $_POST['wc_bsale_admin']['edit'] ) ? 1 : 0,
			'auto_update' => isset( $_POST['wc_bsale_admin']['auto_update'] ) ? 1 : 0
		);

		// Storefront options
		$storefront_settings = array(
			'cart'     => isset( $_POST['wc_bsale_storefront']['cart'] ) ? 1 : 0,
			'checkout' => isset( $_POST['wc_bsale_storefront']['checkout'] ) ? 1 : 0
		);

		// Order processing options
		if ( ! in_array( $_POST['wc_bsale_transversal']['order_event'], array( 'wc', 'custom' ), true ) ) {
			$_POST['wc_bsale_transversal']['order_event'] = 'wc';
		}

		// Valid order statuses
		$order_statuses  = wc_get_order_statuses();
		$selected_status = array_intersect( $_POST['wc_bsale_transversal']['order_status'], array_keys( $order_statuses ) );

		return array(
			'office_id'   => (int) $_POST['wc_bsale_transversal']['office_id'],
			'admin'       => $admin_settings,
			'storefront'  => $storefront_settings,
			'transversal' => array(
				'order_event'  => sanitize_text_field( $_POST['wc_bsale_transversal']['order_event'] ),
				'order_status' => $selected_status,
				'note'         => sanitize_text_field( $_POST['wc_bsale_transversal']['note'] )
			)
		);
	}

	/**
	 * Returns the title of the settings page.
	 *
	 * @return string
	 */
	public function get_setting_title(): string {
		return __( 'Stock synchronization', 'wc-bsale' );
	}

	/**
	 * Loads the resources needed for the stock settings page (styles and scripts).
	 *
	 * @return void
	 */
	public function load_page_resources(): void {
		// Enqueue WooCommerce's admin styles and the product editor styles for Select2
		wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css' );
		wp_enqueue_style( 'woocommerce_product_editor_styles', WC()->plugin_url() . '/assets/client/admin/product-editor/style.css' );

		// WooCommerce JS script for Select2
		wp_enqueue_script( 'wc-enhanced-select' );

		// Enqueue the JavaScript file for the office selection and localize the script with the URL for the AJAX request
		wp_enqueue_script( 'wc-bsale-admin-stock', PLUGIN_URL . 'assets/js/wc-bsale-admin-stock.js', array( 'jquery' ), null, true );
		wp_localize_script( 'wc-bsale-admin-stock', 'stock_parameters', array(
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'placeholder' => 'Search for an office by name',
			'nonce'       => wp_create_nonce( 'search_bsale_data' )
		) );
	}

	/**
	 * Gets the office data from Bsale for the office ID stored in the settings.
	 *
	 * @return void
	 */
	public function load_bsale_office(): void {
		// Check if an office ID is stored in the configuration. If it is, get its name from Bsale to set it as the selected option in the select element
		$office_id = (int) ( $this->settings['office_id'] ?? null );

		if ( $office_id ) {
			$bsale_api_client = new API_Client();

			try {
				$office = $bsale_api_client->get_office_by_id( $office_id );
			} catch ( \Exception $e ) {
				$office = null;
			}

			$this->selected_office = $office ? array( 'id' => $office['id'], 'text' => $office['name'] ) : null;
		}
	}

	/**
	 * Displays the stock settings page.
	 *
	 * @return void
	 */
	public function display_settings(): void {
		$this->load_page_resources();
		$this->load_bsale_office();

		?>
		<div class="wc-bsale-notice wc-bsale-notice-info">
			<p><span class="dashicons dashicons-visibility"></span> For a product or variation to be synchronized with Bsale, it must have a SKU and the "Manage stock" option enabled. Depending on the settings in the "Main" tab, the SKU of the
				product will be used to look for the <code>SKU (code)</code> or <code>barcode (barCode)</code> field in Bsale to match the products.</p>
		</div>
		<?php
		add_settings_section(
			'wc_bsale_office_stock_section',
			'Office selection',
			array( $this, 'office_stock_settings_section_description' ),
			'wc-bsale-settings-stock'
		);

		add_settings_field(
			'wc_bsale_office_stock_edit',
			'Office that manages the stock',
			array( $this, 'office_edit_settings_field_callback' ),
			'wc-bsale-settings-stock',
			'wc_bsale_office_stock_section'
		);

		add_settings_section(
			'wc_bsale_admin_stock_section',
			'Admin integration',
			array( $this, 'admin_stock_settings_section_description' ),
			'wc-bsale-settings-stock'
		);

		add_settings_field(
			'wc_bsale_admin_stock_edit',
			'When editing a product',
			array( $this, 'admin_edit_settings_field_callback' ),
			'wc-bsale-settings-stock',
			'wc_bsale_admin_stock_section'
		);

		add_settings_section(
			'wc_bsale_storefront_stock_section',
			'Storefront integration',
			array( $this, 'storefront_stock_settings_section_description' ),
			'wc-bsale-settings-stock'
		);

		add_settings_field(
			'wc_bsale_storefront_stock_cart',
			'During cart interaction',
			array( $this, 'storefront_cart_settings_field_callback' ),
			'wc-bsale-settings-stock',
			'wc_bsale_storefront_stock_section'
		);

		add_settings_field(
			'wc_bsale_storefront_stock_checkout',
			'During checkout interaction',
			array( $this, 'storefront_checkout_settings_field_callback' ),
			'wc-bsale-settings-stock',
			'wc_bsale_storefront_stock_section'
		);

		add_settings_section(
			'wc_bsale_order_stock_section',
			'Order processing',
			array( $this, 'storefront_order_settings_section_description' ),
			'wc-bsale-settings-stock'
		);

		add_settings_field(
			'wc_bsale_transversal_stock_order',
			'When an order is processed',
			array( $this, 'transversal_order_settings_field_callback' ),
			'wc-bsale-settings-stock',
			'wc_bsale_order_stock_section'
		);

		settings_fields( 'wc_bsale_stock_settings_group' );
		do_settings_sections( 'wc-bsale-settings-stock' );
	}

	public function office_stock_settings_section_description(): void {
		echo '<hr><p>Defines the office that manages the stock of the products in Bsale. This office will be used to get and deduct the stock of the products.</p>';
	}

	public function office_edit_settings_field_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>Office for stock operations</span></legend>
			<select id="wc_bsale_transversal_office_id" name="wc_bsale_transversal[office_id]" style="width: 50%">
				<?php
				if ( $this->selected_office ) {
					echo '<option value="' . $this->selected_office['id'] . '" selected>' . $this->selected_office['text'] . '</option>';
				}
				?>
			</select>
			<p class="description">All the stock operations will be performed using the stock of the products in this office.</p>
			<div class="wc-bsale-notice wc-bsale-notice-info">
				<p><span class="dashicons dashicons-visibility"></span> If you don't see the office you are looking for, please make sure that the office is active in Bsale and its name is not empty.</p>
			</div>
			<div class="wc-bsale-notice wc-bsale-notice-error">
				<p><span class="dashicons dashicons-no-alt"></span> If no office is selected, all the stock operations won't be performed in Bsale, essentially disabling the stock synchronization features.</p>
			</div>
		</fieldset>
		<?php
	}

	public function admin_stock_settings_section_description(): void {
		echo '<hr><p>Settings to manage the stock synchronization between WooCommerce and Bsale on the admin side (back office).</p>';
		echo
		'<div class="wc-bsale-notice wc-bsale-notice-warning">
			<p><span class="dashicons dashicons-warning"></span> When updating the stock of a product from the admin, the update will trigger native WooCommerce events for stock management. Please
				keep this in mind when using features like "Out of stock threshold" and backorders, or when using other plugins that interact with the stock of the products.</p>
		</div>';
	}

	public function admin_edit_settings_field_callback(): void {
		?>
		<fieldset class="wc-bsale-related-fieldset">
			<legend class="screen-reader-text"><span>When editing a product</span></legend>
			<label for="wc_bsale_admin_edit">
				<input type="checkbox" id="wc_bsale_admin_edit" name="wc_bsale_admin[edit]" value="1" <?php checked( 1, $this->settings['admin']['edit'] ?? false ); ?> />
				Check the stock with Bsale when editing a product
			</label>
			<p class="description">When editing a product, the stock will be checked with Bsale. If the stock doesn't match, a confirmation message will be displayed to the user asking if they want
				to update the stock in WooCommerce with the stock in Bsale.</p>
			<br>
			<div style="margin-left: 20px">
				<label for="wc_bsale_admin_auto_update">
					<input type="checkbox" id="wc_bsale_admin_auto_update" name="wc_bsale_admin[auto_update]" value="1" <?php checked( 1, $this->settings['admin']['auto_update'] ?? false ); ?> />
					Don't ask for confirmation when the stock doesn't match and update the stock automatically in WooCommerce
				</label>
				<p class="description">If this option is checked, the stock of the product will automatically be updated in WooCommerce if the stock of the product doesn't match the stock in Bsale.</p>
			</div>
		</fieldset>
		<?php
	}

	public function storefront_stock_settings_section_description(): void {
		echo '<hr><p>Settings to manage the stock synchronization between WooCommerce and Bsale on the storefront side (front office).</p>';
	}

	public function storefront_cart_settings_field_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>During cart interaction</span></legend>
			<label for="wc_bsale_storefront_cart">
				<input type="checkbox" id="wc_bsale_storefront_cart" name="wc_bsale_storefront[cart]" value="1" <?php checked( 1, $this->settings['storefront']['cart'] ?? false ); ?> />
				Update the stock with Bsale when a customer adds a product to its cart
			</label>
			<p class="description">When a customer adds a product to its cart, the stock of that product will be updated with Bsale.</p>
		</fieldset>
		<?php
	}

	public function storefront_checkout_settings_field_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>During checkout interaction</span></legend>
			<label for="wc_bsale_storefront_checkout">
				<input type="checkbox" id="wc_bsale_storefront_checkout" name="wc_bsale_storefront[checkout]" value="1" <?php checked( 1, $this->settings['storefront']['checkout'] ?? false ); ?> />
				Update the stock of the products in the cart at the start of the checkout process
			</label>
			<p class="description">When a customer starts the checkout process, the stock of the products in the cart will be updated with Bsale. If, during this process, the stock of a product is set
				to zero or below because of the data in Bsale, the customer won't be notified immediately. However, WooCommerce will notify the customer of the stock issue when they try to place the order.</p>
		</fieldset>
		<?php
	}

	public function storefront_order_settings_section_description(): void {
		echo '<hr><p>Settings to manage the stock consumption in Bsale when an order is processed in WooCommerce.</p>';
	}

	public function transversal_order_settings_field_callback(): void {
		?>
		<fieldset class="wc-bsale-related-fieldset">
			<legend>When should the stock be deducted (consumed) in Bsale?</legend>
			<label>
				<input type="radio" name="wc_bsale_transversal[order_event]" value="wc" <?php checked( 'wc', $this->settings['transversal']['order_event'] ?? 'wc' ); ?> />
				When WooCommerce reduces the stock
			</label>
			<p class="description">The stock of the products in the order will be deducted on Bsale when WooCommerce reduces the stock. This usually happens when the order status changes to "Processing" or "Completed".</p>
			<br>
			<label>
				<input type="radio" name="wc_bsale_transversal[order_event]" value="custom" <?php checked( 'custom', $this->settings['transversal']['order_event'] ?? 'wc' ); ?> />
				When the order status changes to:
			</label>
			<select id="wc_bsale_transversal_order_status" name="wc_bsale_transversal[order_status][]" multiple="multiple" style="width: 50%">
				<?php
				$selected_statuses = $this->settings['transversal']['order_status'] ?? array( 'wc-processing' );
				foreach ( wc_get_order_statuses() as $status => $label ) {
					echo '<option value="' . $status . '" ' . selected( true === in_array( $status, $selected_statuses ) ) . '>' . $label . '</option>';
				}
				?>
			</select>
			<p class="description">When the order status changes to one of the selected statuses, the stock of the products in the order will be deducted on Bsale.</p>
		</fieldset>
		<div class="wc-bsale-notice wc-bsale-notice-success">
			<p>
				<span class="dashicons dashicons-yes"></span> In any of the cases, the stock of the products in the order will be deducted on Bsale only once, even if multiple events that reduce the stock are triggered in WooCommerce (e.g. when the
				order status changes to "Processing" and then to "Completed").
				The plugin will keep track of the items in the order that has already been deducted on Bsale, preventing the stock from being deducted multiple times.
			</p>
		</div>
		<fieldset>
			<label>Include the following note in the stock consumption operation in Bsale:
				<input type="text" id="wc_bsale_transversal[note]" name="wc_bsale_transversal[note]" value="<?php echo esc_attr( $this->settings['transversal']['note'] ?? '' ); ?>" style="width: 100%">
			</label>
			<p>
				<?php
				$note_preview = str_replace( array( '{1}', '{2}' ), array( get_bloginfo( 'name' ), '123' ), $this->settings['transversal']['note'] );
				?>
				Note preview: <code><?php echo $note_preview; ?></code> (length: <?php echo strlen( $note_preview ); ?> characters)
			</p>
			<p class="description">
				This note will be displayed in Bsale's interface and can be useful to identify the stock consumption operation. The {1} placeholder will be replaced with the name of the store, and the {2} placeholder will be replaced with the order
				number.
				For example, if this setting is set to "{1} - Order {2}", and the store name is "My Store" and the order number is "123", the note in Bsale will say "My Store - Order 123". The maximum length of the formatted note that will be sent to
				Bsale is 100 characters.
			</p>
		</fieldset>
		<?php
	}
}