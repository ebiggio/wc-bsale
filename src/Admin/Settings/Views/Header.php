<?php
/**
 * Display the header for the settings page.
 *
 * @package WC_Bsale
 * @class   Header
 */

namespace WC_Bsale\Admin\Settings\Views;

defined( 'ABSPATH' ) || exit;

use const WC_Bsale\PLUGIN_URL;

/**
 * Header class
 */
class Header {
	public function __construct( $settings_classes_map, $current_tab) {
		?>
		<div class="wrap">
			<h1><img src="<?php echo PLUGIN_URL . 'assets/images/bsale_icon.png' ?>" alt=""> WooCommerce Bsale plugin settings</h1>

			<?php settings_errors( 'wc_bsale_messages' ); ?>

			<nav class="nav-tab-wrapper">
				<?php
				foreach ( $settings_classes_map as $slug => $setting_class ) {
					echo '<a href="' . esc_html( admin_url( 'admin.php?page=wc-bsale-settings&tab=' . esc_attr( $slug ) ) ) . '" class="nav-tab ' . ( $current_tab === $slug ? 'nav-tab-active' : '' ) . '">' . esc_html( $setting_class->get_setting_title() ) . '</a>';
				}
				?>
			</nav>
			<form action="options.php" method="POST">
		<?php
	}
}