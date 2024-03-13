<?php
/**
 * Stock settings page.
 *
 * @class   Stock_Settings
 * @package WC_Bsale
 */

// TODO Sanitize and validate the input before saving it to the database
namespace WC_Bsale\Admin\Settings;

use WC_Bsale\Bsale_API_Client;

use const WC_Bsale\PLUGIN_URL;

defined( 'ABSPATH' ) || exit;

/**
 * Stock_Settings class
 */
class Stock_Settings {
	private mixed $admin_settings;
	private mixed $storefront_settings;
	private mixed $selected_office = null;

	public function __construct() {
		$this->admin_settings      = maybe_unserialize( get_option( 'wc_bsale_admin_stock' ) );
		$this->storefront_settings = maybe_unserialize( get_option( 'wc_bsale_storefront_stock' ) );

		// Check if an office ID is stored in the configuration. If it is, get its name from Bsale to set it as the selected option in the select element
		$office_id = (int) ($this->storefront_settings['order_officeid'] ?? null);

		if ( $office_id ) {
			$bsale_api_client = new Bsale_API_Client();

			try {
				$office = $bsale_api_client->get_office_by_id( $office_id );
			} catch ( \Exception $e ) {
				$office = null;
			}

			$this->selected_office = $office ? array( 'id' => $office['id'], 'text' => $office['name'] ) : null;
		}

		// Enqueue the Select2 plugin
		wp_enqueue_style( 'wc-bsale-admin-stock', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css' );
		wp_enqueue_script( 'wc-bsale-admin-stock-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js' );

		// Enqueue the JavaScript file for the office selection and localize the script with the URL for the AJAX request
		wp_enqueue_script( 'wc-bsale-admin-stock', PLUGIN_URL . 'assets/js/wc-bsale-admin-stock.js', array( 'jquery' ), null, true );
		wp_localize_script( 'wc-bsale-admin-stock', 'bsale_offices', array(
			'offices_ajax_url'    => admin_url( 'admin-ajax.php' ),
			'select2_placeholder' => 'Search for an office...',
		) );

		$this->settings_page_content();
	}

	/**
	 * Search offices by name on Bsale and send them as a JSON response formatted for the Select2 plugin.
	 *
	 * This method is expected to be called via an AJAX request, with the 'term' parameter set to the search term.
	 * If there is an error getting the offices from Bsale, an error message will be sent as a disabled option for the Select2 plugin.
	 *
	 * @return void
	 */
	public static function search_bsale_offices(): void {
		$term = sanitize_text_field($_GET['term']);

		$bsale_offices = array();

		$bsale_api_client = new Bsale_API_Client();

		try {
			$bsale_offices = $bsale_api_client->search_offices_by_name( $term );
		} catch ( \Exception $e ) {
			// Send an error message as a disabled option for the Select2 plugin
			wp_send_json( array(
				'results' => array(
					array(
						'id'       => '-1',
						'text'     => 'There was an error getting the offices from Bsale: ' . $e->getMessage(),
						'disabled' => true,
					),
				),
			) );

			wp_die();
		}

		// Transform the array of offices into an array of objects with the required format for the Select2 plugin
		$bsale_offices = array_map( function ( $office ) {
			return array(
				'id'   => $office['id'],
				'text' => $office['name'],
			);
		}, $bsale_offices );

		wp_send_json(array('results' => $bsale_offices));

		wp_die();
	}

	/**
	 * Manages the stock settings page.
	 *
	 * @return void
	 */
	public function settings_page_content(): void {
		?>
		<div class="wc-bsale-notice wc-bsale-notice-info">
			<p><span class="dashicons dashicons-visibility"></span> For a product to be synchronized with Bsale, it must have a SKU and the "Manage stock" option enabled.</p>
		</div>
		<?php
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
			'wc_bsale_stock_section',
			'Storefront integration',
			array( $this, 'storefront_stock_settings_section_description' ),
			'wc-bsale-settings-stock'
		);

		add_settings_field(
			'wc_bsale_storefront_stock_cart',
			'During cart interaction',
			array( $this, 'storefront_cart_settings_field_callback' ),
			'wc-bsale-settings-stock',
			'wc_bsale_stock_section'
		);

		add_settings_field(
			'wc_bsale_storefront_stock_checkout',
			'During checkout interaction',
			array( $this, 'storefront_checkout_settings_field_callback' ),
			'wc-bsale-settings-stock',
			'wc_bsale_stock_section'
		);

		add_settings_field(
			'wc_bsale_storefront_order',
			'After an order is placed',
			array( $this, 'storefront_order_settings_field_callback' ),
			'wc-bsale-settings-stock',
			'wc_bsale_stock_section'
		);

		settings_fields( 'wc_bsale_stock_settings_group' );
		do_settings_sections( 'wc-bsale-settings-stock' );
	}

	public function admin_stock_settings_section_description(): void {
		echo '<p>Settings to manage the stock synchronization between WooCommerce and Bsale on the admin side (back office).</p>';
		echo
		'<div class="wc-bsale-notice wc-bsale-notice-warning">
			<p><span class="dashicons dashicons-warning"></span> When updating the stock of a product from the admin, the update will trigger native WooCommerce events for stock management. Please
				keep this in mind when using features like "Out of stock threshold" and backorders, or when using other plugins that interact with the stock of the products.</p>
		</div>';
	}

	public function admin_edit_settings_field_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>When editing a product</span></legend>
			<label for="wc_bsale_admin_stock_edit">
				<input type="checkbox" id="wc_bsale_admin_stock_edit" name="wc_bsale_admin_stock[edit]" value="1" <?php checked( 1, $this->admin_settings['edit'] ?? false ); ?> />
				Check the stock with Bsale when editing a product
			</label>
			<p class="description">When editing a product, the stock will be checked with Bsale. If the stock doesn't match, a confirmation message will be displayed to the user asking if they want
				to update the stock in WooCommerce with the stock in Bsale.</p>
		</fieldset>
		<br>
		<div style="margin-left: 20px">
			<fieldset>
				<label for="wc_bsale_admin_stock_auto_update">
					<input type="checkbox" id="wc_bsale_admin_stock_auto_update" name="wc_bsale_admin_stock[auto_update]" value="1" <?php checked( 1, $this->admin_settings['auto_update'] ?? false ); ?> />
					Don't ask for confirmation when the stock doesn't match and update the stock automatically in WooCommerce
				</label>
				<p class="description">If this option is checked, the stock of the product will automatically be updated in WooCommerce if the stock of the product doesn't match the stock in Bsale.</p>
			</div>
		</fieldset>
		<?php
	}

	public function storefront_stock_settings_section_description(): void {
		echo '<p>Settings to manage the stock synchronization between WooCommerce and Bsale on the storefront side (front office).</p>';
	}

	public function storefront_cart_settings_field_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>During cart interaction</span></legend>
			<label for="wc_bsale_storefront_stock_cart">
				<input type="checkbox" id="wc_bsale_storefront_stock_cart" name="wc_bsale_storefront_stock[cart]" value="1" <?php checked( 1, $this->storefront_settings['cart'] ?? false ); ?> />
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
			<label for="wc_bsale_storefront_stock_checkout">
				<input type="checkbox" id="wc_bsale_storefront_stock_checkout" name="wc_bsale_storefront_stock[checkout]" value="1" <?php checked( 1, $this->storefront_settings['checkout'] ?? false ); ?> />
				Update the stock of the products in the cart at the start of the checkout process
			</label>
			<p class="description">When a customer starts the checkout process, the stock of the products in the cart will be updated with Bsale. If, during this process, the stock of a product is set
				to zero or below because of the data in Bsale, the customer won't be notified immediately. However, WooCommerce will notify the customer of the stock issue when they try to place the order.</p>
		</fieldset>
		<?php
	}

	public function storefront_order_settings_field_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>After an order is placed</span></legend>
			<label for="wc_bsale_storefront_order" style="display: inline">
				Deduct (consume) the stock of the products in the order on Bsale on this office:
				<select id="wc_bsale_storefront_order_officeid" name="wc_bsale_storefront_stock[order_officeid]" style="width: 25%">
					<?php
					if ( $this->selected_office ) {
						echo '<option value="' . $this->selected_office['id'] . '" selected>' . $this->selected_office['text'] . '</option>';
					}
					?>
				</select>
			</label>
			<p class="description">After an order is placed, the stock of the products in the order will be deducted (consumed) on Bsale on the selected office. If no office is selected, no stock will be deducted.</p>
			<div class="wc-bsale-notice wc-bsale-notice-info">
				<p><span class="dashicons dashicons-visibility"></span> If you don't see the office you are looking for, please make sure that the office is active in Bsale and its name is not empty.</p>
			</div>
		</fieldset>
		<br>
		<?php
	}
}