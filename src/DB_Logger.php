<?php
/**
 * Logs operations in the "wc_bsale_operation_log" table.
 *
 * @class   DB_Logger
 * @package WC_Bsale
 */

namespace WC_Bsale;

use WC_Bsale\Interfaces\Observer;

defined( 'ABSPATH' ) || exit;

/**
 * DB_Logger class
 *
 * Singleton class.
 *
 * @implements Observer Interface
 */
class DB_Logger implements Observer {
	private static ?DB_Logger $instance = null;

	public static function get_instance(): ?DB_Logger {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Logs an operation in the "wc_bsale_operation_log" table.
	 *
	 * @param array $parameters The parameters to log.
	 *
	 * @return void
	 */
	public function log( array $parameters ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_bsale_operation_log';

		$wpdb->insert(
			$table_name,
			[
				'operation_datetime' => current_time( 'mysql' ),
				...$parameters
			]
		);
	}

	// Prevent object cloning
	private function __clone() {
	}

	// Prevent object instantiation
	private function __construct() {
	}

	// Observer interface implementation
	public function update( string $event_trigger, string $event_type, string $identifier, string $message, string $result_code = 'info' ): void {
		$parameters = [
			'event_trigger' => $event_trigger,
			'event_type'    => $event_type,
			'identifier'    => $identifier,
			'message'       => $message,
			'result_code'   => $result_code
		];

		$this->log( $parameters );
	}
}
