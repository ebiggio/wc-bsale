<?php
/**
 * Cron settings page.
 *
 * @class   Cron_Settings
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin\Settings;

use const WC_Bsale\PLUGIN_URL;

defined( 'ABSPATH' ) || exit;

/**
 * Cron_Settings class
 */
class Cron_Settings {
	private mixed $settings;
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
	private string $cron_endpoint_url;
	private array $products = array();
	private array $excluded_products = array();

	public function __construct() {
		// Load the settings from the database
		$this->settings = maybe_unserialize( get_option( 'wc_bsale_cron' ) );

		// If no settings from the database are found, set the default values
		if ( ! $this->settings ) {
			$this->settings = array(
				'catalog'           => 'all',
				'products'          => array(),
				'excluded_products' => array(),
				'fields'            => array(),
				'mode'              => 'external',
				'secret_key'        => '',
			);
		}

		$secret_key = $this->settings['secret_key'];

		// Check if a secret key has been generated for the cron URL. If not, generate one
		if ( ! $secret_key ) {
			$this->settings['secret_key'] = wp_generate_password( 26, false );
		}

		// Generate the custom cron URL
		$base_url                = home_url( '/' );
		$this->cron_endpoint_url = add_query_arg( 'wc_bsale_cron', $this->settings['secret_key'], $base_url );
	}

	/**
	 * Validates the data of the cron settings form.
	 *
	 * If a value is not valid, it will silently be set to a default value.
	 *
	 * @return array
	 */
	public function validate_cron_settings(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error( 'wc_bsale_messages', 'wc_bsale_message', 'Insufficient permissions' );

			return array();
		}

		// Check the settings for the products section
		$valid_catalog_settings = array( 'all', 'specific', 'none' );
		if ( ! in_array( $_POST['wc_bsale_cron']['catalog'], $valid_catalog_settings, true ) ) {
			$_POST['wc_bsale_cron']['catalog'] = 'all';
		}

		$products          = $_POST['wc_bsale_cron']['products'] ?? array();
		$excluded_products = $_POST['wc_bsale_cron']['excluded_products'] ?? array();

		// Validate the field checkboxes
		$valid_fields                     = array( 'description', 'stock', 'status' );
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
	 * Loads WooCommerce products and excluded products from the settings.
	 *
	 * If the settings have products selected, we load them and store them in the $products property, using their IDs. The same goes for the excluded products, which are stored in the $excluded_products property.
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
	 * Loads the resources for the settings page.
	 *
	 * @return void
	 */
	private function load_page_resources(): void {
		// Enqueue Select2 CSS
		wp_enqueue_style( 'woocommerce_select2', WC()->plugin_url() . '/assets/css/select2.css' );

		// Enqueue WooCommerce's admin styles and the product editor styles for the product search with Select2
		wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css' );
		wp_enqueue_style( 'woocommerce_product_editor_styles', WC()->plugin_url() . '/assets/client/admin/product-editor/style.css' );

		// WooCommerce admin script for product search
		wp_enqueue_script( 'wc-enhanced-select' );

		wp_enqueue_script( 'wc-bsale-admin-cron', PLUGIN_URL . 'assets/js/wc-bsale-admin-cron.js', array( 'jquery' ), null, true );
	}

	/**
	 * Displays the cron settings page.
	 *
	 * @return void
	 */
	public function settings_page_content(): void {
		$this->load_wc_products();
		$this->load_page_resources();

		add_settings_section(
			'wc_bsale_cron_products_section',
			'What to sync with Bsale?',
			array( $this, 'cron_products_section_description' ),
			'wc-bsale-settings-cron'
		);

		add_settings_field(
			'wc_bsale_cron_products',
			'Sync the following products with Bsale',
			array( $this, 'cron_products_callback' ),
			'wc-bsale-settings-cron',
			'wc_bsale_cron_products_section'
		);

		add_settings_field(
			'wc_bsale_cron_excluded_products',
			'Don\'t sync these products',
			array( $this, 'cron_excluded_products_callback' ),
			'wc-bsale-settings-cron',
			'wc_bsale_cron_products_section'
		);

		add_settings_section(
			'wc_bsale_cron_fields_section',
			'Which fields to sync with Bsale?',
			array( $this, 'cron_fields_section_description' ),
			'wc-bsale-settings-cron'
		);

		add_settings_field(
			'wc_bsale_cron_field_options',
			'Sync these fields with Bsale',
			array( $this, 'cron_field_options_callback' ),
			'wc-bsale-settings-cron',
			'wc_bsale_cron_fields_section'
		);

		add_settings_section(
			'wc_bsale_cron_mode_section',
			'How to sync with Bsale?',
			array( $this, 'cron_mode_section_description' ),
			'wc-bsale-settings-cron'
		);

		add_settings_field(
			'wc_bsale_cron_sync_mode',
			'Sync mode',
			array( $this, 'cron_mode_callback' ),
			'wc-bsale-settings-cron',
			'wc_bsale_cron_mode_section'
		);

		settings_fields( 'wc_bsale_cron_settings_group' );
		do_settings_sections( 'wc-bsale-settings-cron' );
	}

	public function cron_products_section_description(): void {
		echo '<hr><p>Settings that define what elements of the product catalog will be synced with the data in Bsale.</p>';
	}

	public function cron_products_callback(): void {
		?>
		<fieldset class="wc-bsale-related-fieldset">
			<legend class="screen-reader-text"><span>Sync the following products with Bsale</span></legend>
			<label>
				<input type="radio" name="wc_bsale_cron[catalog]" value="all" <?php checked( 'all', $this->settings['catalog'] ); ?>>
				All the products
			</label>
			<p class="description">Sync all the products and their variations, both active and inactive, with Bsale</p>
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
				<select id="wc_bsale_cron_products" name="wc_bsale_cron[products][]" class="wc-product-search" data-action="woocommerce_json_search_products_and_variations" multiple="multiple" style="width: 300px">
					<?php
					if ( $this->products ) {
						foreach ( $this->products as $product ) {
							echo '<option value="' . esc_attr( $product->get_id() ) . '" selected="selected">' . esc_html( $product->get_name() ) . '</option>';
						}
					}
					?>
				</select>
			</label>
			<br>
			<label>
				<input type="radio" name="wc_bsale_cron[catalog]" value="none" <?php checked( 'none', $this->settings['catalog'] ); ?>>
				No sync
			</label>
			<p class="description">Disables all cron syncs with Bsale. Other settings (such as the stock synchronization) are not affected.</p>
		</fieldset>
		<?php
	}

	public function cron_excluded_products_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>Don't sync these products</span></legend>
			<label>
				<select id="wc_bsale_cron_excluded_products" name="wc_bsale_cron[excluded_products][]" class="wc-product-search" multiple="multiple" style="width: 300px">
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

	public function cron_fields_section_description(): void {
		echo '<hr><p>Settings for specifying which fields of the products will be synced with Bsale.</p>';
	}

	public function cron_field_options_callback(): void {
		?>
		<fieldset>
			<legend class="screen-reader-text"><span>Sync these fields with Bsale</span></legend>
			<label>
				<input type="checkbox" name="wc_bsale_cron[fields][description]" value="description" <?php checked( in_array( 'description', $this->settings['fields'], true ) ); ?>>
				Description
			</label>
			<br>
			<label>
				<input type="checkbox" name="wc_bsale_cron[fields][stock]" value="stock" <?php checked( in_array( 'stock', $this->settings['fields'], true ) ); ?>>
				Stock
			</label>
			<br>
			<label>
				<input type="checkbox" name="wc_bsale_cron[fields][status]" value="status" <?php checked( in_array( 'status', $this->settings['fields'], true ) ); ?>>
				Status
			</label>
		</fieldset>
		<?php
	}

	public function cron_mode_section_description(): void {
		echo '<hr><p>Settings for specifying how the sync with Bsale will be performed.</p>';
	}

	public function cron_mode_callback(): void {
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