<?php
/**
 * Interface for the classes that will consume the Bsale API.
 *
 * @class   API_Consumer
 * @package WC_Bsale
 */

namespace WC_Bsale\Interfaces;

/**
 * API_Consumer interface
 */
interface API_Consumer {
	/**
	 * Adds an observer to the list of observers.
	 *
	 * @param \WC_Bsale\Interfaces\Observer $observer
	 *
	 * @return void
	 */
	public function add_observer( Observer $observer ): void;

	/**
	 * Notifies all the observers of an event.
	 *
	 * @param string $event_trigger The event that triggered the operation.
	 * @param string $event_type    The type of event (for example, stock update, invoice generation, etc.).
	 * @param string $identifier    An identifier for the event (for example, the product ID, the order ID, etc.).
	 * @param string $message       The message to log, with details about the operation.
	 * @param string $result_code   Result code for the operation. Can only be 'info', 'success', 'warning' or 'error'.
	 *
	 * @return void
	 */
	public function notify_observers( string $event_trigger, string $event_type, string $identifier, string $message, string $result_code = 'info' ): void;
}