<?php
/**
 * Executes the cron jobs of the plugin.
 *
 * @class   Cron
 * @package WC_Bsale
 */

namespace WC_Bsale;

// For now, this class is empty. It will be used to execute the cron jobs of the plugin.
class Cron {
	public function __construct() {
	}

	public function run(): void {
		error_log('Cron job executed!');
	}
}
