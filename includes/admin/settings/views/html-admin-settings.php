<?php

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

$current_tab = $_GET['tab'] ?? '';
global $settings_tabs;

?>
<div class="wrap">
	<h1><img src="<?php echo WC_BSALE_PLUGIN_URL . 'assets/images/bsale_icon.png' ?>"> WooCommerce Bsale plugin settings</h1>

	<?php settings_errors('wc_bsale_messages'); ?>

	<nav class="nav-tab-wrapper">
		<?php
		foreach ( $settings_tabs as $slug => $label ) {
			echo '<a href="' . esc_html( admin_url( 'admin.php?page=wc-bsale-settings&tab=' . esc_attr( $slug ) ) ) . '" class="nav-tab ' . ( $current_tab === $slug ? 'nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
		}
		?>
	</nav>
	<form action="options.php" method="POST">