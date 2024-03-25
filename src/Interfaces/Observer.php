<?php
/**
 * Interface for the observer pattern.
 *
 * @class   Observer
 * @package WC_Bsale
 */

namespace WC_Bsale\Interfaces;

/**
 * Observer interface
 */
interface Observer {
	/**
	 * Updates the observer with details about an operation.
	 *
	 * @param string $event_trigger The event that triggered the operation.
	 * @param string $event_type    The type of event (for example, stock update, invoice generation, etc.).
	 * @param string $identifier    An identifier for the event (for example, the product ID, the order ID, etc.).
	 * @param string $message       The message to log, with details about the operation.
	 * @param string $result_code   Result code for the operation. Can only be 'info', 'success', 'warning' or 'error'.
	 *
	 * @return void
	 */
	public function update( string $event_trigger, string $event_type, string $identifier, string $message, string $result_code = 'info' ): void;
}