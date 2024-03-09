<?php
/**
 * WC Bsale Admin Hooks
 *
 * This class contains the hooks for the admin side of the plugin
 *
 * @package WC_Bsale
 */

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

/**
 * WC Bsale Admin Hooks class
 */
class WC_Bsale_Admin_Hooks {
	private mixed $admin_stock_settings;

	public function __construct() {
		$this->admin_stock_settings = maybe_unserialize( get_option( 'wc_bsale_admin_stock' ) );

		// Check if any of the stock settings for the admin side are enabled. If not, we don't need to add the hooks
		if ( ! $this->admin_stock_settings ) {
			return;
		}

		require_once WC_BSALE_PLUGIN_DIR . '/includes/bsale/class-wc-bsale-api.php';

		add_action( 'load-post.php', array( $this, 'wc_bsale_product_edit_hook' ) );
	}

	/**
	 * Shows a dismissible notice in the admin
	 *
	 * @param string $message The message to show, escaped for safe use in HTML (i.e., passed through esc_html() or similar)
	 * @param string $type    The type of notice. Can be 'info', 'success', 'warning' or 'error'
	 *
	 * @return void
	 */
	private function show_admin_notice( string $message, string $type = 'info' ): void {
		add_action( 'admin_notices', function () use ( $message, $type ) {
			?>
			<div class="notice notice-<?php esc_attr_e( $type ); ?> is-dismissible">
				<p><?php echo $message; ?></p>
			</div>
			<?php
		} );
	}

	public function wc_bsale_product_edit_hook(): void {
		$screen = get_current_screen();

		if ( ! $screen->id === 'product' ) {
			// We are not in the product edit screen
			return;
		}

		if ( ! isset( $_GET['post'] ) ) {
			// We are not editing a product (probably creating a new one or saving changes)
			return;
		}

		// Get the product and its SKU
		$product = wc_get_product( $_GET['post'] );
		$sku     = $product->get_sku();

		// Check if the product is marked with the "Manage stock" option. If not, we show a message to the user notifying them of this, and suggesting to enable this option
		if ( ! $product->managing_stock() ) {
			$message = esc_html__( 'This product is not marked with the "Manage stock" option. If you would like to sync its stock with Bsale, please enable this option.', 'wc-bsale' );
			$this->show_admin_notice( $message );

			return;
		}

		// If the product has no SKU, we can't sync it with Bsale. We then show a message to the user notifying them of this, and suggesting to add a SKU to the product
		if ( empty( $sku ) ) {
			$message = esc_html__( 'This product has no SKU. If you would like to sync its stock with Bsale, please add a SKU to it.', 'wc-bsale' );
			$this->show_admin_notice( $message );

			return;
		}

		// TODO Check if product is variable and sync all variations
		$wc_stock    = (int) get_post_meta( $product->get_id(), '_stock', true );
		$bsale_api   = new WC_Bsale_API();
		$bsale_stock = $bsale_api->get_stock_by_code( $sku );

		// If the product has no stock in Bsale, we show a message to the user notifying them of this
		// TODO $bsale_stock can be false because of an error in the API request. We should handle this case (perhaps by returning a WP_Error object from the API class and checking for it here)
		if ( $bsale_stock === false ) {
			$message = sprintf( esc_html__( 'No stock was found in Bsale for the code [%s]. Please check if this product\'s SKU exists in Bsale.', 'wc-bsale' ), $sku );
			$this->show_admin_notice( $message, 'warning' );

			return;
		}

		if ( $wc_stock === $bsale_stock ) {
			$message = esc_html__( 'The stock of this product is the same as the stock in Bsale.', 'wc-bsale' );
			$this->show_admin_notice( $message, 'success' );

			return;
		}

		// If the user has clicked the "Sync Stock" button or the settings to sync automatically is enabled, we update the product's stock with the stock in Bsale
		if ( isset( $_POST['wc_bsale_sync_stock'] ) || isset( $this->admin_stock_settings['auto_update'] ) ) {
			wc_update_product_stock( $product, $bsale_stock );

			$message = esc_html__( 'The stock of this product has been synced with the stock in Bsale.', 'wc-bsale' );
			$this->show_admin_notice( $message, 'success' );

			return;
		}

		add_action( 'admin_notices', function () use ( $wc_stock, $bsale_stock ) {
			$message = esc_html__( 'The stock of this product [%s] is different than the stock in Bsale [%s]. Would you like to sync them?', 'wc-bsale' );
			?>
			<div class="notice notice-warning is-dismissible">
				<p><?php echo sprintf( $message, $wc_stock, $bsale_stock ); ?></p>
				<form action="" method="POST">
					<input type="hidden" name="wc_bsale_sync_stock" value="1"/>
					<?php submit_button( esc_html__( 'Update stock with Bsale' ) ); ?>
				</form>
			</div>
			<?php
		} );
	}
}

new WC_Bsale_Admin_Hooks();