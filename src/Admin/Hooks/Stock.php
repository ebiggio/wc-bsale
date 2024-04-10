<?php
/**
 * Hooks for the admin side of the plugin related to stock syncing.
 *
 * @class   Stock
 * @package WC_Bsale
 */

namespace WC_Bsale\Admin\Hooks;

defined( 'ABSPATH' ) || exit;

use WC_Bsale\Bsale\API_Client;

/**
 * Stock class
 *
 * This class doesn't implement the API_Consumer interface, since the results of the operations are shown directly to the user in the admin through notices.
 */
class Stock {
	/**
	 * The ID of the office to sync the stock with.
	 *
	 * @var int
	 */
	private int $office_id;
	/**
	 * The stock settings for the admin, loaded from the Stock class in the admin settings.
	 *
	 * @see \WC_Bsale\Admin\Settings\Stock Stock settings class.
	 * @var array
	 */
	private array $admin_stock_settings;

	public function __construct() {
		$stock_settings = \WC_Bsale\Admin\Settings\Stock::get_settings();

		// If there are no stock settings, we don't need to add the hooks
		if ( ! $stock_settings ) {
			return;
		}

		// Check if the office ID is set. If not, we don't need to add the hooks
		if ( ! $stock_settings['office_id'] ) {
			return;
		}

		$this->office_id = (int) $stock_settings['office_id'];

		$this->admin_stock_settings = $stock_settings['admin'];

		// Check if the "edit" setting is enabled. If not, we don't need to add the hooks (since the setting "auto_update" depends on this one)
		if ( ! $this->admin_stock_settings['edit'] ) {
			return;
		}

		add_action( 'load-post.php', array( $this, 'wc_bsale_product_edit_hook' ) );
	}

	/**
	 * Shows a dismissible notice in the admin.
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

	/**
	 * Checks if the product is a variable or not, and syncs its stock with Bsale if needed.
	 *
	 * For a product or variation to be synced, it needs to have a SKU and be marked with the "Manage stock" option. It also needs to have stock in Bsale.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function wc_bsale_product_edit_hook(): void {
		$screen = get_current_screen();

		if ( 'product' !== $screen->id ) {
			// We are not in the product edit screen
			return;
		}

		if ( ! isset( $_GET['post'] ) ) {
			// We are not editing a product (probably creating a new one or saving changes)
			return;
		}

		$product = wc_get_product( $_GET['post'] );

		// Check if the product is a variable product. If so, we don't need to sync its stock, but the stock of its variations
		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_children();

			// Store the variations that need to be synced
			$variations_to_sync = array();

			foreach ( $variations as $variation_id ) {
				$variation = wc_get_product( $variation_id );

				// For a variation to be synced, it needs to have a SKU and be marked with the "Manage stock" option
				if ( ! empty( $variation->get_sku() && $variation->managing_stock() ) ) {
					$variations_to_sync[ $variation_id ] = $variation;
				}
			}

			// We compare the total variations and the ones that qualify for syncing. If they are not the same, we show a message to the user notifying them of this
			if ( count( $variations ) !== count( $variations_to_sync ) ) {
				$message = esc_html__( 'This variable product has variations that are not marked with the "Manage stock" option or have no SKU. If you would like to sync their stock with Bsale, please enable this option and add a SKU to them.', 'wc-bsale' );
				$this->show_admin_notice( $message, 'warning' );
			}

			// If there are no variations to sync, we don't need to continue
			if ( empty( $variations_to_sync ) ) {
				return;
			}

			$bsale_api = new API_Client();

			// If the user has clicked the "Sync Stock" button or the settings to sync automatically is enabled, we update the stock of the variations with the stock in Bsale
			if ( isset( $_POST['wc_bsale_sync_stock'] ) || $this->admin_stock_settings['auto_update'] ) {
				$variations_synced = array();

				foreach ( $variations_to_sync as $variation_id => $variation ) {
					$sku = $variation->get_sku();

					try {
						$bsale_stock = $bsale_api->get_stock_by_identifier( $sku, $this->office_id );
					} catch ( \Exception $e ) {
						$message = sprintf( esc_html__( 'An error occurred while trying to fetch the stock of the variation [%s] from Bsale. Please try again later.', 'wc-bsale' ), $variation->get_sku() );
						$this->show_admin_notice( $message, 'error' );

						continue;
					}

					if ( false !== $bsale_stock ) {
						wc_update_product_stock( $variation, $bsale_stock );
						$variations_synced[ $variation_id ] = $variation;
					}
				}
			}

			foreach ( $variations_to_sync as $variation_id => $variation ) {
				if ( isset( $variations_synced[ $variation_id ] ) ) {
					$message = sprintf( esc_html__( 'The stock of the variation [%s] has been synced with the stock in Bsale.', 'wc-bsale' ), $variation->get_sku() );
					$this->show_admin_notice( $message, 'success' );

					continue;
				}

				$sku = $variation->get_sku();

				try {
					$bsale_stock = $bsale_api->get_stock_by_identifier( $sku, $this->office_id );
				} catch ( \Exception $e ) {
					$message = sprintf( esc_html__( 'An error occurred while trying to fetch the stock of the variation [%s] from Bsale. Please try again later.', 'wc-bsale' ), $variation->get_sku() );
					$this->show_admin_notice( $message, 'error' );

					continue;
				}

				// If the variation has no stock in Bsale, we show a message to the user notifying them of this
				if ( false === $bsale_stock ) {
					$message = sprintf( esc_html__( 'No stock was found in Bsale for [%s]. Please check if this variation\'s SKU exists in Bsale, and has stock for the selected office.', 'wc-bsale' ), $sku );
					$this->show_admin_notice( $message, 'warning' );

					continue;
				}

				// If the variation has stock in Bsale, we compare it with the stock in WooCommerce
				$wc_stock = (int) get_post_meta( $variation->get_id(), '_stock', true );

				if ( $wc_stock === $bsale_stock ) {
					$message = sprintf( esc_html__( 'The stock of the variation [%s] is the same as the stock in Bsale.', 'wc-bsale' ), $variation->get_sku() );
					$this->show_admin_notice( $message, 'success' );
				} else {
					add_action( 'admin_notices', function () use ( $wc_stock, $bsale_stock, $variation ) {
						$message = sprintf( esc_html__( 'The stock of the variation [%s] (%s) is different than the stock in Bsale (%s). Would you like to sync them?', 'wc-bsale' ), $variation->get_sku(), $wc_stock, $bsale_stock );
						?>
						<div class="notice notice-warning is-dismissible">
							<p><?php echo $message; ?></p>
							<form action="" method="POST">
								<input type="hidden" name="wc_bsale_sync_stock" value="1"/>
								<?php submit_button( esc_html__( 'Update stock with Bsale' ) ); ?>
							</form>
						</div>
						<?php
					} );
				}
			}

			return;
		}

		/*
		 * If we reach this point, it means that the product is not a variable product. We then need to sync its stock with Bsale
		 */

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

		$wc_stock  = (int) get_post_meta( $product->get_id(), '_stock', true );
		$bsale_api = new API_Client();

		try {
			$bsale_stock = $bsale_api->get_stock_by_identifier( $sku, $this->office_id );
		} catch ( \Exception $e ) {
			$message = sprintf( esc_html__( 'An error occurred while trying to fetch the stock of the product [%s] from Bsale. Please try again later.', 'wc-bsale' ), $sku );
			$this->show_admin_notice( $message, 'error' );

			return;
		}

		// If the product has no stock in Bsale, we show a message to the user notifying them of this
		if ( false === $bsale_stock ) {
			$message = sprintf( esc_html__( 'No stock was found in Bsale for [%s]. Please check if this product\'s SKU exists in Bsale, and has stock for the selected office.', 'wc-bsale' ), $sku );
			$this->show_admin_notice( $message, 'warning' );

			return;
		}

		if ( $wc_stock === $bsale_stock ) {
			$message = esc_html__( 'The stock of this product is the same as the stock in Bsale.', 'wc-bsale' );
			$this->show_admin_notice( $message, 'success' );

			return;
		}

		// If the user has clicked the "Sync Stock" button or the settings to sync automatically is enabled, we update the product's stock with the stock in Bsale
		if ( isset( $_POST['wc_bsale_sync_stock'] ) || $this->admin_stock_settings['auto_update'] ) {
			wc_update_product_stock( $product, $bsale_stock );

			$message = esc_html__( 'The stock of this product has been synced with the stock in Bsale.', 'wc-bsale' );
			$this->show_admin_notice( $message, 'success' );

			return;
		}

		add_action( 'admin_notices', function () use ( $wc_stock, $bsale_stock ) {
			$message = esc_html__( 'The stock of this product (%s) is different than the stock in Bsale (%s). Would you like to sync them?', 'wc-bsale' );
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