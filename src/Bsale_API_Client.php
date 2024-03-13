<?php
/**
 * Manages the communication with the Bsale API.
 *
 * @class   Bsale_API_Client
 * @package WC_Bsale
 */

namespace WC_Bsale;

defined( 'ABSPATH' ) || exit;

/**
 * Bsale_API_Client class
 */
class Bsale_API_Client {
	private string $api_url;
	private string $access_token;
	private array|null $bsale_response = null;
	private \WP_Error|null $bsale_wp_error = null;

	public function __construct() {
		$this->api_url      = 'https://api.bsale.io/v1/';
		$this->access_token = get_option( 'wc_bsale_sandbox_access_token' );
	}

	/**
	 * Makes an HTTP GET request to the Bsale API.
	 *
	 * @param string $endpoint The endpoint to request.
	 *
	 * @return mixed The response from the API.
	 * @throws \Exception If there was an error making the request.
	 */
	private function make_request( string $endpoint ): mixed {
		// Check if the access token is set and throw an exception if it's not
		if ( '' === $this->access_token ) {
			throw new \Exception( 'The Bsale API access token is not set.' );
		}

		$this->bsale_response = null;
		$this->bsale_wp_error = null;

		$args = array(
			'headers' => array(
				'access_token' => $this->access_token,
			),
		);

		$bsale_response = wp_remote_get( $this->api_url . $endpoint, $args );

		if ( is_wp_error( $bsale_response ) || 200 !== wp_remote_retrieve_response_code( $bsale_response ) ) {
			if ( is_wp_error( $bsale_response ) ) {
				$this->bsale_wp_error = $bsale_response;
			} else {
				$this->bsale_wp_error = new \WP_Error( wp_remote_retrieve_response_code( $bsale_response ), wp_remote_retrieve_response_message( $bsale_response ) );
			}

			throw new \Exception( 'Error making the request to the Bsale API: ' . $this->bsale_wp_error->get_error_message() );
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
	 * Retrieves a product's stock by its code.
	 *
	 * @param $code string The product's code. We assume that, in WooCommerce, the code is the product's SKU.
	 *
	 * @return int|bool The product's stock, or false if an empty code was provided or if no stock was found in Bsale.
	 * @throws \Exception If there was an error fetching the stock from Bsale.
	 */
	public function get_stock_by_code( string $code ): bool|int {
		if ( '' === $code ) {
			return false;
		}

		$api_endpoint = 'stocks.json?code=' . $code;

		$stock_list = $this->make_request( $api_endpoint );

		if ( 0 === $stock_list->count ) {
			// No stock found for the code provided (doesn't mean that the product doesn't exist in Bsale; just that it has no stock)
			return false;
		}

		// Get only the first item of the collection
		$stock = $stock_list->items[0];

		return (int) $stock->quantityAvailable;
	}
}