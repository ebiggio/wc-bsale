<?php
/**
 * Stock settings page.
 *
 * @class   Stock_Settings
 * @package WC_Bsale
 */

// TODO Sanitize and validate the input before saving it to the database
namespace WC_Bsale\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Stock_Settings class
 */
class Stock_Settings {
	private mixed $admin_settings;
	private mixed $storefront_settings;

	public function __construct() {
		$this->admin_settings      = maybe_unserialize( get_option( 'wc_bsale_admin_stock' ) );
		$this->storefront_settings = maybe_unserialize( get_option( 'wc_bsale_storefront_stock' ) );

		$this->settings_page_content();
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
			</fieldset>
		</div>
		<script>
            function updateAutoCheckboxStatus() {
                document.getElementById('wc_bsale_admin_stock_auto_update').disabled = !document.getElementById('wc_bsale_admin_stock_edit').checked;
            }

            document.getElementById('wc_bsale_admin_stock_edit').addEventListener('change', updateAutoCheckboxStatus);
            document.addEventListener('DOMContentLoaded', updateAutoCheckboxStatus);
		</script>
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
}