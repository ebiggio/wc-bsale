<?php
/**
 * Interface for the settings classes.
 *
 * @package WC_Bsale
 */

namespace WC_Bsale\Interfaces;

/**
 * Setting interface
 *
 * This interface defines the methods that a settings class must implement.
 * A settings class is a class that is responsible for displaying the settings page and validating the settings form data submitted by the user.
 * It must also provide a static method to get the settings stored in the database, which should return a set of default values if there are no settings stored.
 *
 * @property array $settings The settings that govern the specific settings page.
 */
interface Setting {
	/**
	 * Returns the settings stored in the database. If there are no settings stored, a set of default values should be returned.
	 *
	 * @return array
	 */
	public static function get_settings(): array;

	/**
	 * Validates the settings form data, returning the validated settings to be stored in the database.
	 *
	 * This method should check if the current user has the necessary permissions to save the settings, returning an empty array if they don't.
	 *
	 * If an invalid setting is detected, it should be silently discarded and replaced with a default value, if necessary.
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
	public function display_settings(): void;
}