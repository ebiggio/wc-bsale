<?php
/**
 * Entry point for the plugin's storefront functionality.
 *
 * @class   Storefront_Init
 * @package WC_Bsale
 */

namespace WC_Bsale\Storefront;

defined( 'ABSPATH' ) || exit;

/**
 * Storefront_Init class
 */
class Storefront_Init {
	public function __construct() {
		// Add the front hooks for the stock synchronization
		new Hooks\Stock();
	}
}