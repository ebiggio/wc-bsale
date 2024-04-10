<?php
/**
 * Interface for the observer pattern.
 *
 * @package WC_Bsale
 */

namespace WC_Bsale\Interfaces;

/**
 * Observer interface
 *
 * This interface defines the methods that an observer class must implement to use the observer pattern.
 * An observer is a class that will be notified when an event is triggered and will log the operation, send notifications, etc.
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