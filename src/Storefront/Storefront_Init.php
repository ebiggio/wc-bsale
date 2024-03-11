<?php

namespace WC_Bsale\Storefront;

class Storefront_Init {
	public function __construct() {
		// Add the front hooks for the stock synchronization
		new Hooks\Stock();
	}
}