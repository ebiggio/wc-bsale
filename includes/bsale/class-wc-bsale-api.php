<?php

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

class WC_Bsale_API {
	private string $api_url;
	private string $access_token;
	private array|null $bsale_response = null;
	private \WP_Error|null $bsale_wp_error = null;

	public function __construct() {
		$this->api_url      = 'https://api.bsale.io/v1/';
		$this->access_token = get_option( 'wc_bsale_sandbox_access_token' );
	}

	/**
	 * Makes an HTTP GET request to the Bsale API
	 *
	 * @param string $endpoint The endpoint to request
	 *
	 * @return false|mixed The response from the API, or false if there was an error making the request
	 */
	private function make_api_request( string $endpoint ): mixed {
		// TODO Check if the access token is set and log an error if it's not
		$this->bsale_response = null;
		$this->bsale_wp_error = null;

		$args = array(
			'headers' => array(
				'access_token' => $this->access_token,
			),
		);

		$bsale_response = wp_remote_get( $this->api_url . $endpoint, $args );

		if ( is_wp_error( $bsale_response ) ) {
			$this->bsale_wp_error = $bsale_response;

			return false;
		}

		$this->bsale_response = $bsale_response;

		return json_decode( wp_remote_retrieve_body( $this->bsale_response ) );
	}

	public function get_last_response(): array|null {
		return $this->bsale_response;
	}

	public function get_last_wp_error(): \WP_Error|null {
		return $this->bsale_wp_error;
	}

	/**
	 * Retrieves a product's stock by its code
	 *
	 * @param $code string The product's code
	 *
	 * @return int|bool The product's stock, or false if an empty code was provided or if no stock was found in Bsale
	 */
	public function get_stock_by_code( string $code ): bool|int {
		if ( $code === '' ) {
			return false;
		}

		$api_endpoint = 'stocks.json?code=' . $code;

		if ( ! $stock_list = $this->make_api_request( $api_endpoint ) ) {
			// There was an error making the request
			// TODO Log the error
			return false;
		}

		if ( $stock_list->count === 0 ) {
			// No stock found for the code provided (doesn't mean that the product doesn't exist in Bsale; just that it has no stock)
			return false;
		}

		// Get only the first item of the collection
		$stock = $stock_list->items[0];

		// TODO Could also be $stock->quantity?
		return (int)$stock->quantityAvailable;
	}
}