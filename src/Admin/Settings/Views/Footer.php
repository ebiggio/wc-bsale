<?php
/**
 * Displays the footer of the settings page.
 *
 * @package WC_Bsale
 * @class   Footer
 */

namespace WC_Bsale\Admin\Settings\Views;

defined( 'ABSPATH' ) || exit;

use const WC_Bsale\PLUGIN_VERSION;

/**
 * Footer class
 */
class Footer {
	public function __construct() {
		// Submit button for the form
		submit_button();
		?>
		</form>
		<div class="wc-bsale-footer">
			<p>
				<?php
				printf(
				/* translators: %s: plugin name */
					esc_html__( 'Thank you for using the %s.', 'wc-bsale' ),
					'<strong>WooCommerce Bsale plugin</strong>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
				/* translators: %s: plugin name */
					esc_html__( 'For more information about it\'s development, please visit the %s.', 'wc-bsale' ),
					'<a href="https://github.com/ebiggio/wc-bsale" target="_blank" rel="noopener noreferrer">GitHub repository</a>'
				);
				?>
			</p>
			<small>
				<?php
				printf(
					esc_html__( 'Please note that this integration is intended to be used by WooCommerce stores for the chilean market.', 'wc-bsale' ));
				?>
			</small>
			<p style="text-align: right">
				<?php
				printf(
				/* translators: %s: plugin version */
					esc_html__( 'Version %s', 'wc-bsale' ),
					PLUGIN_VERSION
				);
				?>
			</p>
		</div>
		</div>
		<?php
	}
}