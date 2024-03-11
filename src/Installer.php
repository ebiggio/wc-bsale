<?php
/**
 * Installation related functions and actions.
 *
 * @class   Installer
 * @package WC_Bsale
 */

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

/**
 * Installer class
 */
class Installer {
	public function __construct() {
		$this->create_tables();
	}

	private function create_tables(): void {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		global $wpdb;

		$table_name      = $wpdb->prefix . 'wc_bsale_operation_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql =
			"CREATE TABLE $table_name (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				operation_datetime DATETIME NOT NULL,
				event_trigger VARCHAR(100) NOT NULL,
				event_type VARCHAR(100) NOT NULL,
				identifier VARCHAR(255),
				message TEXT NOT NULL,
				result_code ENUM('success', 'error', 'warning', 'info') NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;";

		dbDelta( $sql );
	}
}

new Installer();