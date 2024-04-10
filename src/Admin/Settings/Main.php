<?php
/**
 * Main settings page.
 *
 * @class   Main
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use WC_Bsale\Interfaces\Setting as Setting_Interface;

/**
 * Main settings class
 */
class Main implements Setting_Interface {
	/**
	 * The main settings.
	 *
	 * @var array
	 */
	private $settings;

	public function __construct() {
		$this->settings = self::get_settings();
	}

	/**
	 * @inheritdoc
	 */
	public static function get_settings(): array {
		$settings = maybe_unserialize( get_option( 'wc_bsale_main' ) );

		if ( ! $settings ) {
			$settings = array(
				'sandbox_access_token' => '',
				'product_identifier'   => 'code'
			);
		}

		return $settings;
	}

	/**
	 * @inheritdoc
	 */
	public function validate_settings(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_messages', __( 'You do not have sufficient permissions to access this page.' ) );

			return array();
		}

		if ( ! isset( $_POST['wc_bsale_main']['sandbox_access_token'] ) || '' === $_POST['wc_bsale_main']['sandbox_access_token'] ) {
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_messages', __( 'The Bsale API access token is required.' ) );

			$_POST['wc_bsale_main']['sandbox_access_token'] = '';
		}

		$valid_product_identifier = array( 'code', 'barcode' );
		if ( ! isset( $_POST['wc_bsale_main']['product_identifier'] ) || ! in_array( $_POST['wc_bsale_main']['product_identifier'], $valid_product_identifier ) ) {
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_messages', __( 'The product identifier is required.' ) );

			$_POST['wc_bsale_main']['product_identifier'] = 'code';
		}

		return array(
			'sandbox_access_token' => sanitize_text_field( $_POST['wc_bsale_main']['sandbox_access_token'] ),
			'product_identifier'   => sanitize_text_field( $_POST['wc_bsale_main']['product_identifier'] )
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_setting_title(): string {
		return __('Main settings', 'wc-bsale');
	}

	/**
	 * @inheritdoc
	 */
	public function display_settings(): void {
		add_settings_section(
			'wc_bsale_main_section',
			'Bsale API configuration',
			array( $this, 'settings_section_description' ),
			'wc-bsale-settings'
		);

		add_settings_field(
			'wc_bsale_sandbox_access_token',
			'Sandbox access token',
			array( $this, 'sandbox_access_token_callback' ),
			'wc-bsale-settings',
			'wc_bsale_main_section'
		);

		add_settings_section(
			'wc_bsale_product_identifier_section',
			'Product identifier in Bsale',
			array( $this, 'product_identifier_section_description' ),
			'wc-bsale-settings'
		);

		add_settings_field(
			'wc_bsale_product_identifier',
			'Use the product\'s SKU of WooCommerce to match',
			array( $this, 'product_identifier_callback' ),
			'wc-bsale-settings',
			'wc_bsale_product_identifier_section'
		);

		settings_fields( 'wc_bsale_main_settings_group' );
		do_settings_sections( 'wc-bsale-settings' );
	}

	/**
	 * Callback for the settings section description.
	 *
	 * @return void
	 */
	public function settings_section_description(): void {
		echo '<hr><p>Settings for connecting with the Bsale API.</p>';
	}

	/**
	 * Callback for the sandbox access token field.
	 *
	 * @return void
	 */
	public function sandbox_access_token_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>Sandbox access token</span></legend>
			<label>
				<input type="text" name="wc_bsale_main[sandbox_access_token]" value="<?php echo esc_attr( $this->settings['sandbox_access_token'] ); ?>" style="width: 400px"/>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the product identifier section description.
	 *
	 * @return void
	 */
	public function product_identifier_section_description(): void {
		echo '<hr><p>Defines which field in Bsale will be used to identify the products to match them with WooCommerce\'s SKUs.</p>';
	}

	/**
	 * Callback for the product identifier field.
	 *
	 * @return void
	 */
	public function product_identifier_callback(): void {
		?>
		<fieldset class="wc-bsale-related-fieldset">
			<legend class="screen-reader-text"><span>Product identifier in Bsale</span></legend>
			<label>
				<input type="radio" name="wc_bsale_main[product_identifier]" value="code" <?php checked( $this->settings['product_identifier'], 'code' ); ?> />
				The product code (SKU) in Bsale
			</label>
			<br>
			<label>
				<input type="radio" name="wc_bsale_main[product_identifier]" value="barcode" <?php checked( $this->settings['product_identifier'], 'barcode' ); ?> />
				The product's barcode in Bsale
			</label>
		</fieldset>
		<?php
	}

	public function get_access_token(): string {
		return $this->settings['sandbox_access_token'];
	}
}