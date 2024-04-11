<?php
/**
 * Cron settings page.
 *
 * @class   Cron
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use WC_Bsale\Interfaces\Setting as Setting_Interface;
use const WC_Bsale\PLUGIN_URL;

/**
 * Cron settings class
 */
class Cron implements Setting_Interface {
	/**
	 * The settings for the cron functionality.
	 *
	 * @var array
	 */
	private array $settings;
	/**
	 * Valid times that can be selected for the cron sync.
	 *
	 * @var array
	 */
	private array $valid_cron_times = array(
		'0000' => '00:00',
		'0100' => '01:00',
		'0200' => '02:00',
		'0300' => '03:00',
		'0400' => '04:00',
		'0500' => '05:00',
		'0600' => '06:00',
		'0700' => '07:00',
		'0800' => '08:00',
		'0900' => '09:00',
		'1000' => '10:00',
		'1100' => '11:00',
		'1200' => '12:00',
		'1300' => '13:00',
		'1400' => '14:00',
		'1500' => '15:00',
		'1600' => '16:00',
		'1700' => '17:00',
		'1800' => '18:00',
		'1900' => '19:00',
		'2000' => '20:00',
		'2100' => '21:00',
		'2200' => '22:00',
		'2300' => '23:00'
	);
	/**
	 * The URL for the custom cron endpoint.
	 *
	 * @var string
	 */
	private string $cron_endpoint_url;
	/**
	 * The products that are selected for the cron sync, as WooCommerce product objects (\WC_Product)
	 *
	 * @var array
	 */
	private array $products = array();
	/**
	 * The products that are excluded from the cron sync, as WooCommerce product objects (\WC_Product)
	 *
	 * @var array
	 */
	private array $excluded_products = array();

	public function __construct() {
		$this->settings = self::get_settings();

		$secret_key = $this->settings['secret_key'];

		// Check if a secret key has been generated for the cron URL. If not, generate one
		if ( ! $secret_key ) {
			$this->settings['secret_key'] = wp_generate_password( 26, false );
		}

		// Generate the custom cron URL
		$base_url                = home_url( '/' );
		$this->cron_endpoint_url = add_query_arg( array( 'wc_bsale' => 'run_cron', 'secret_key' => $this->settings['secret_key'] ), $base_url );
	}

	/**
	 * @inheritDoc
	 */
	public static function get_settings(): array {
		$settings = maybe_unserialize( get_option( 'wc_bsale_cron' ) );

		// If no settings from the database are found, set the default values
		if ( ! $settings ) {
			$settings = array(
				'enabled'           => 0,
				'catalog'           => 'all',
				'products'          => array(),
				'excluded_products' => array(),
				'fields'            => array( 'status' ),
				'mode'              => 'external',
				'time'              => '0000',
				'secret_key'        => '',
			);
		}

		return $settings;
	}

	/**
	 * @inheritDoc
	 */
	public function validate_settings(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_message', __( 'You do not have sufficient permissions to access this page.', 'wc-bsale' ) );

			return array();
		}

		// Check the settings for the products section
		$valid_catalog_settings = array( 'all', 'specific' );
		if ( ! in_array( $_POST['wc_bsale_cron']['catalog'], $valid_catalog_settings, true ) ) {
			$_POST['wc_bsale_cron']['catalog'] = 'all';
		}

		$products          = $_POST['wc_bsale_cron']['products'] ?? array();
		$excluded_products = $_POST['wc_bsale_cron']['excluded_products'] ?? array();

		// There should be at least one field selected. Otherwise, the cron sync won't do anything
		if ( empty( $_POST['wc_bsale_cron']['fields'] ) ) {
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_message', __( 'You must select at least one field to sync with Bsale.', 'wc-bsale' ) );

			$_POST['wc_bsale_cron']['fields'] = array( 'status' );
		}

		// Validate the field checkboxes
		$valid_fields                     = array( 'status', 'description', 'stock' );
		$_POST['wc_bsale_cron']['fields'] = array_intersect( $_POST['wc_bsale_cron']['fields'], $valid_fields );

		// Check the sync mode
		$valid_sync_modes = array( 'external', 'wp' );
		if ( ! in_array( $_POST['wc_bsale_cron']['mode'], $valid_sync_modes, true ) ) {
			$_POST['wc_bsale_cron']['mode'] = 'external';
		}

		// Validate the time for the cron sync. If the time is not valid, set it to 00:00
		if ( ! array_key_exists( $_POST['wc_bsale_cron']['time'], $this->valid_cron_times ) ) {
			$_POST['wc_bsale_cron']['time'] = '0000';
		}

		// If the user clicked the "Generate new secret key" button, delete the current secret key. The new one will be generated in the constructor
		if ( isset( $_POST['generate_secret_key'] ) ) {
			$secret_key = '';
		} else {
			$secret_key = $this->settings['secret_key'];
		}

		$next_cron_job_timestamp = wp_next_scheduled( 'wc_bsale_cron' );
		// Check the cron mode. If it's set to "wp", schedule the cron event if it's not already scheduled
		if ( 'wp' === $_POST['wc_bsale_cron']['mode'] ) {
			// Get WordPress's configured timezone
			$timezone = get_option( 'timezone_string' ) ?: 'UTC';
			date_default_timezone_set( $timezone );

			// Calculate the timestamp for the selected time
			$settings_timestamp = strtotime( 'today ' . $this->valid_cron_times[ $_POST['wc_bsale_cron']['time'] ] );

			if ( ! $next_cron_job_timestamp ) {
				wp_schedule_event( $settings_timestamp, 'daily', 'wc_bsale_cron' );
			} elseif ( $next_cron_job_timestamp !== $settings_timestamp ) {
				// If the cron event is already scheduled but the time has changed, reschedule it
				wp_unschedule_event( $next_cron_job_timestamp, 'wc_bsale_cron' );
				wp_schedule_event( $settings_timestamp, 'daily', 'wc_bsale_cron' );
			}
		} else {
			// If the cron mode is set to "external", unschedule the cron event if it's already scheduled
			if ( $next_cron_job_timestamp ) {
				wp_unschedule_event( $next_cron_job_timestamp, 'wc_bsale_cron' );
			}
		}

		return array(
			'enabled'           => isset( $_POST['wc_bsale_cron']['enabled'] ) ? 1 : 0,
			'catalog'           => sanitize_text_field( $_POST['wc_bsale_cron']['catalog'] ),
			'products'          => array_map( 'intval', $products ),
			'excluded_products' => array_map( 'intval', $excluded_products ),
			'fields'            => array_map( 'sanitize_text_field', $_POST['wc_bsale_cron']['fields'] ),
			'mode'              => sanitize_text_field( $_POST['wc_bsale_cron']['mode'] ),
			'time'              => sanitize_text_field( $_POST['wc_bsale_cron']['time'] ),
			'secret_key'        => $secret_key,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_setting_title(): string {
		return __( 'Cron settings', 'wc-bsale' );
	}

	/**
	 * Loads WooCommerce products and excluded products from the settings.
	 *
	 * If the settings have products selected, we load them by their IDs and store them in the $products property.
	 * The same goes for the excluded products, which are stored in the $excluded_products property.
	 *
	 * @return void
	 */
	private function load_wc_products(): void {
		if ( $this->settings['products'] ) {
			$this->products = array_map( 'wc_get_product', $this->settings['products'] );
		}

		if ( $this->settings['excluded_products'] ) {
			$this->excluded_products = array_map( 'wc_get_product', $this->settings['excluded_products'] );
		}
	}

	/**
	 * Loads the resources needed for the cron settings page (styles and scripts).
	 *
	 * @return void
	 */
	private function load_page_resources(): void {
		// Enqueue WooCommerce's admin styles and the product editor styles for the product search with Select2
		wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css' );
		wp_enqueue_style( 'woocommerce_product_editor_styles', WC()->plugin_url() . '/assets/client/admin/product-editor/style.css' );

		// WooCommerce admin script for product search
		wp_enqueue_script( 'wc-enhanced-select' );

		wp_enqueue_script( 'wc-bsale-admin-cron', PLUGIN_URL . 'assets/js/wc-bsale-admin-cron.js', array( 'jquery' ), null, true );
	}

	/**
	 * @inheritDoc
	 */
	public function display_settings(): void {
		$this->load_wc_products();
		$this->load_page_resources();

		add_settings_section(
			'wc_bsale_cron_enabled_section',
			'Cron status',
			array( $this, 'enabled_description' ),
			'wc-bsale-settings-cron'
		);

		add_settings_field(
			'wc_bsale_cron_enabled',
			'Enable cron process?',
			array( $this, 'enabled_callback' ),
			'wc-bsale-settings-cron',
			'wc_bsale_cron_enabled_section'
		);

		add_settings_section(
			'wc_bsale_cron_products_section',
			'What to sync with Bsale?',
			array( $this, 'products_section_description' ),
			'wc-bsale-settings-cron'
		);

		add_settings_field(
			'wc_bsale_cron_products',
			'Sync the following products with Bsale',
			array( $this, 'products_callback' ),
			'wc-bsale-settings-cron',
			'wc_bsale_cron_products_section'
		);

		add_settings_field(
			'wc_bsale_cron_excluded_products',
			'Don\'t sync these products',
			array( $this, 'excluded_products_callback' ),
			'wc-bsale-settings-cron',
			'wc_bsale_cron_products_section'
		);

		add_settings_section(
			'wc_bsale_cron_fields_section',
			'Which fields to sync with Bsale?',
			array( $this, 'fields_section_description' ),
			'wc-bsale-settings-cron'
		);

		add_settings_field(
			'wc_bsale_cron_field_options',
			'Sync these fields with Bsale',
			array( $this, 'field_options_callback' ),
			'wc-bsale-settings-cron',
			'wc_bsale_cron_fields_section'
		);

		add_settings_section(
			'wc_bsale_cron_mode_section',
			'How to sync with Bsale?',
			array( $this, 'mode_section_description' ),
			'wc-bsale-settings-cron'
		);

		add_settings_field(
			'wc_bsale_cron_sync_mode',
			'Sync mode',
			array( $this, 'mode_callback' ),
			'wc-bsale-settings-cron',
			'wc_bsale_cron_mode_section'
		);

		settings_fields( 'wc_bsale_cron_settings_group' );
		do_settings_sections( 'wc-bsale-settings-cron' );
	}

	/**
	 * Callback for the enabled section description.
	 *
	 * @return void
	 */
	public function enabled_description(): void {
		?>
		<hr><p><?php esc_html_e( 'Enable or disable the cron process. This only affects the cron sync with Bsale; other settings (such as the stock synchronization) are not affected.', 'wc-bsale' ); ?></p>
		<?php
	}

	/**
	 * Callback for the enabled field.
	 *
	 * @return void
	 */
	public function enabled_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Enable cron process?', 'wc-bsale' ); ?></span></legend>
			<label>
				<input type="checkbox" name="wc_bsale_cron[enabled]" value="1" <?php checked( 1, $this->settings['enabled'] ); ?>>
				<?php esc_html_e( 'Enable cron process', 'wc-bsale' ); ?>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the products section description.
	 *
	 * @return void
	 */
	public function products_section_description(): void {
		echo '<hr><p>Settings that define what elements of the product catalog will be synced with the data in Bsale.</p>';
	}

	/**
	 * Callback for the products to sync field.
	 *
	 * @return void
	 */
	public function products_callback(): void {
		?>
		<fieldset class="wc-bsale-related-fieldset">
			<legend class="screen-reader-text"><span>Sync the following products with Bsale</span></legend>
			<label>
				<input type="radio" name="wc_bsale_cron[catalog]" value="all" <?php checked( 'all', $this->settings['catalog'] ); ?>>
				All the products
			</label>
			<p class="description">Sync all the products and their variations, both active and inactive, with Bsale.</p>
			<div class="wc-bsale-notice wc-bsale-notice-warning">
				<p><span class="dashicons dashicons-warning"></span> The sync with Bsale is a complex process that can affect the performance of the site. If you have a large catalog of products, we recommend that you use the "Specific products"
					option and select only the products that you want to sync with Bsale.</p>
			</div>
			<label>
				<input type="radio" name="wc_bsale_cron[catalog]" value="specific" <?php checked( 'specific', $this->settings['catalog'] ); ?>>
				Specific products
			</label>
			<p class="description">Only the products that are selected in the following list.</p>
			<label>
				<select id="wc_bsale_cron_products" name="wc_bsale_cron[products][]" class="wc-product-search" data-action="woocommerce_json_search_products_and_variations" multiple="multiple" style="width: 500px">
					<?php
					if ( $this->products ) {
						foreach ( $this->products as $product ) {
							echo '<option value="' . esc_attr( $product->get_id() ) . '" selected="selected">' . esc_html( $product->get_name() ) . '</option>';
						}
					}
					?>
				</select>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the excluded products field.
	 *
	 * @return void
	 */
	public function excluded_products_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>Don't sync these products</span></legend>
			<label>
				<select id="wc_bsale_cron_excluded_products" name="wc_bsale_cron[excluded_products][]" class="wc-product-search" multiple="multiple" style="width: 500px">
					<?php
					if ( $this->excluded_products ) {
						foreach ( $this->excluded_products as $product ) {
							echo '<option value="' . esc_attr( $product->get_id() ) . '" selected="selected">' . esc_html( $product->get_name() ) . '</option>';
						}
					}
					?>
				</select>
			</label>
			<p class="description">If you don't want to sync certain products with Bsale, add them to this list.</p>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the fields section description.
	 *
	 * @return void
	 */
	public function fields_section_description(): void {
		echo '<hr><p>Settings for specifying which fields of the products will be synced with Bsale.</p>';
	}

	/**
	 * Callback for the field options.
	 *
	 * @return void
	 */
	public function field_options_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>Sync these fields with Bsale</span></legend>
			<label>
				<input type="checkbox" name="wc_bsale_cron[fields][status]" value="status" <?php checked( in_array( 'status', $this->settings['fields'], true ) ); ?>>
				Status
			</label>
			<br>
			<label>
				<input type="checkbox" name="wc_bsale_cron[fields][description]" value="description" <?php checked( in_array( 'description', $this->settings['fields'], true ) ); ?>>
				Description
			</label>
			<br>
			<label>
				<input type="checkbox" name="wc_bsale_cron[fields][stock]" value="stock" <?php checked( in_array( 'stock', $this->settings['fields'], true ) ); ?>>
				Stock
			</label>
			<div class="wc-bsale-notice wc-bsale-notice-warning">
				<p>
					<span class="dashicons dashicons-info"></span>
					To sync the stock of the products with Bsale, you need to set an office in the <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-bsale-settings&tab=stock' ) ); ?>">Stock synchronization</a> settings.
					If you don't set an office, the stock sync won't be performed.
				</p>
			</div>
			<div class="wc-bsale-notice wc-bsale-notice-info">
				<p>
					<span class="dashicons dashicons-visibility"></span>
					Remember that for a product's stock to be synced with Bsale, the product (or its variations) must have a SKU and its stock management must be enabled.
				</p>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Callback for the mode section description.
	 *
	 * @return void
	 */
	public function mode_section_description(): void {
		echo '<hr><p>Settings for specifying how the sync with Bsale will be performed.</p>';
	}

	/**
	 * Callback for the mode field.
	 *
	 * @return void
	 */
	public function mode_callback(): void {
		?>
		<fieldset class="wc-bsale-related-fieldset">
			<legend class="screen-reader-text"><span>Sync mode</span></legend>
			<label>
				<input type="radio" name="wc_bsale_cron[mode]" value="external" <?php checked( 'external', $this->settings['mode'] ); ?>>
				Use an external cron job for the sync
			</label>
			<p class="description">
				You will need to set up a cron job in your server or in an external system to run the sync script at the desired time. Use the following URL to set up the cron job:
				<input type="text" value="<?php echo esc_url( $this->cron_endpoint_url ); ?>" style="width: 100%;" readonly="readonly">
				For example, on a Unix-based server, you can use the following command in the crontab to run the sync every day at 00:00:<br>
				<code>0 0 * * * curl -s '<?php echo esc_url( $this->cron_endpoint_url ); ?>'</code>
			</p>
			<p class="description">
				You can also generate a new secret key by clicking the following button:
				<input type="submit" name="generate_secret_key" class="button" value="Generate new secret key">
				<br>
				If you do so, remember to update the cron job with the new URL.
			</p>
			<br>
			<label>
				<input type="radio" name="wc_bsale_cron[mode]" value="wp" <?php checked( 'wp', $this->settings['mode'] ); ?>>
				Use WP-Cron for the sync
			</label>
			<div class="wc-bsale-notice wc-bsale-notice-warning">
				<p>
					<span class="dashicons dashicons-warning"></span>
					A note about WP-Cron:<br>
					WP-Cron is a system that runs the scheduled tasks of WordPress. It is not a real cron job, but a pseudo-cron that runs when a user visits the site. If the site doesn't receive visits, the sync won't run. Please
					keep this in mind when selecting this option and the time of the sync, since it will depend on the visits to the site during or around the selected time for the sync to be fired.
				</p>
			</div>
			<label>
				Run the syn each day at
				<select name="wc_bsale_cron[time]">
					<?php
					foreach ( $this->valid_cron_times as $key => $value ) {
						echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $this->settings['time'], false ) . '>' . esc_html( $value ) . '</option>';
					}
					?>
				</select>
			</label>
		</fieldset>
		<?php
	}
}