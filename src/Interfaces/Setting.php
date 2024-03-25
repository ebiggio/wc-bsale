<?php
/**
 * Interface for the settings classes.
 *
 * @class   Setting
 * @package WC_Bsale
 */

namespace WC_Bsale\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Setting interface
 */
interface Setting {
	/**
	 * Validates the settings form data.
	 *
	 * @return array The validated settings to be stored in the database.
	 */
	public function validate_settings( ): array;

	/**
	 * Returns the title of the settings page.
	 *
	 * @return string
	 */
	public function get_setting_title(): string;

	/**
	 * Displays the settings page.
	 *
	 * @return void
	 */
	public function display_settings_page(): void;
}