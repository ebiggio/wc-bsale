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
	private string $product_identifier;
	private array|null $bsale_response = null;
	private \WP_Error|null $bsale_wp_error = null;

	public function __construct() {
		$this->api_url = 'https://api.bsale.io/v1/';

		$main_settings            = maybe_unserialize( get_option( 'wc_bsale_main' ) );
		$this->access_token       = $main_settings['sandbox_access_token'] ?? '';
		$this->product_identifier = $main_settings['product_identifier'] ?? '';
	}

	/**
	 * Makes a request to the Bsale API.
	 *
	 * @param string     $endpoint The endpoint to request.
	 * @param string     $method   The HTTP method to use. Defaults to 'GET'.
	 * @param array|null $body     The body of the request. Defaults to null.
	 *
	 * @return object An object representing the response body.
	 * @throws \Exception If the access token is not set, there was an error making the request, or if the response code is not 2xx.
	 */
	private function make_request( string $endpoint, string $method = 'GET', array $body = null ): mixed {
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

		if ( 'POST' === $method ) {
			$args['method'] = 'POST';
			$args['body']   = json_encode( $body );
		}

		$bsale_response = wp_remote_request( $this->api_url . $endpoint, $args );

		if ( is_wp_error( $bsale_response ) || 2 !== (int) ( wp_remote_retrieve_response_code( $bsale_response ) / 100 ) ) {
			if ( is_wp_error( $bsale_response ) ) {
				$this->bsale_wp_error = $bsale_response;
			} else {
				$response_body = json_decode( wp_remote_retrieve_body( $bsale_response ) );

				if ( isset( $response_body->error ) ) {
					$this->bsale_wp_error = new \WP_Error( wp_remote_retrieve_response_code( $bsale_response ), $response_body->error );
				} else {
					$this->bsale_wp_error = new \WP_Error( wp_remote_retrieve_response_code( $bsale_response ), wp_remote_retrieve_response_message( $bsale_response ) );
				}
			}

			throw new \Exception( 'Error making the request to the Bsale API: ' . $this->bsale_wp_error->get_error_message() );
		}

		$this->bsale_response = $bsale_response;

		return json_decode( wp_remote_retrieve_body( $this->bsale_response ) );
	}

	/**
	 * Retrieves the last **successful** response from Bsale.
	 *
	 * @param bool $body_only If true, will return only the body of the response. Defaults to false.
	 *
	 * @return array|null The last successful response from Bsale, or null if no request was made or if the last request was not successful.
	 */
	public function get_last_response( bool $body_only = false ): array|null {
		if ( $body_only ) {
			return json_decode( wp_remote_retrieve_body( $this->bsale_response ) );
		}

		return $this->bsale_response;
	}

	/**
	 * Retrieves the last error from Bsale.
	 *
	 * @return \WP_Error|null The last error from Bsale as a \WP_Error object, or null if no request was made or if there was no error in the last request.
	 */
	public function get_last_wp_error(): \WP_Error|null {
		return $this->bsale_wp_error;
	}

	/**
	 * Retrieves a product's **available** stock by its identifier from a specific office in Bsale.
	 *
	 * @param $identifier string The product's identifier. Can be the product's code or barcode.
	 * @param $office_id  int The ID of the office to get the stock from.
	 *
	 * @return int|bool The product's stock, or false if an empty identifier or office ID was provided or if no stock was found in Bsale.
	 * @throws \Exception If there was an error fetching the stock from Bsale.
	 */
	public function get_stock_by_identifier( string $identifier, int $office_id ): bool|int {
		if ( '' === $identifier || 0 === $office_id ) {
			return false;
		}

		$api_endpoint = 'stocks.json?' . $this->product_identifier . '=' . $identifier . '&officeid=' . $office_id;

		$office_stock = $this->make_request( $api_endpoint );

		if ( 0 === $office_stock->count ) {
			// No stock found for the identifier provided (doesn't mean that the product doesn't exist in Bsale; just that it has no stock in the office)
			return false;
		}

		// Return the available stock of the product
		return (int) $office_stock->items[0]->quantityAvailable;
	}

	/**
	 * Searches for **active** offices in Bsale by their names and returns their IDs and names.
	 *
	 * @param string $name The name of the office to search for.
	 *
	 * @return array The list of offices that matches the name provided. Will be empty if no offices were found or if an empty name was provided.
	 * @throws \Exception If there was an error fetching the list of offices from Bsale.
	 */
	public function search_offices_by_name( string $name ): array {
		$offices = array();

		if ( '' === $name ) {
			return $offices;
		}

		$name = urlencode( $name );

		$offices_list = $this->make_request( 'offices.json?state=1&fields=[name]&name=' . $name );

		foreach ( $offices_list->items as $office ) {
			$offices[] = array(
				'id'   => $office->id,
				'name' => $office->name
			);
		}

		return $offices;
	}

	/**
	 * Gets an **active** office from Bsale by its ID.
	 *
	 * @param int $office_id The ID of the office to get.
	 *
	 * @return array The office's data. Will be empty if the office was not found, an empty ID was provided, or if the office is not active.
	 * @throws \Exception If there was an error fetching the office from Bsale.
	 */
	public function get_office_by_id( int $office_id ): array {
		if ( 0 === $office_id ) {
			return array();
		}

		try {
			$office = $this->make_request( 'offices/' . $office_id . '.json' );
		} catch ( \Exception $e ) {
			// If the error code is 404, it means that the office was not found. That's a valid case, so we return an empty array. In any other situation, we throw the exception
			if ( 404 !== $this->bsale_wp_error->get_error_code() ) {
				throw $e;
			}

			return array();
		}

		// Check the office's state. If it's not active, we return an empty array
		if ( 1 !== $office->state ) {
			return array();
		}

		return (array) $office;
	}

	/**
	 * Consumes stock of products in Bsale.
	 *
	 * @param string $note      A description of the stock consumption. Will be displayed in Bsale's interface. Max length will be set to 100 characters.
	 * @param int    $office_id The ID of the office to consume the stock from.
	 * @param array  $products  An array of products to consume the stock from. Each product must have an SKU and a 'quantity' key, and the 'quantity' must be greater than 0.
	 *
	 * @return bool True if the stock was consumed successfully for all the products. False if an empty note or office ID was provided, or if there was an error consuming the stock.
	 */
	public function consume_stock( string $note, int $office_id, array $products ): bool {
		if ( 0 === $office_id ) {
			return false;
		}

		$api_endpoint = 'stocks/consumptions.json';

		$products_to_consume = array();

		/*
		 * In the sandbox environment, the use of the 'barcode' identifier is inconsistent: while it works for the stock retrieval as 'barcode', it doesn't work for
		 * the stock consumption unless it's used as 'barCode' (case-sensitive).
		 * TODO Check if this inconsistency is present in the production environment
		 */
		$product_identifier = 'barcode' === $this->product_identifier ? 'barCode' : 'code';

		foreach ( $products as $product ) {
			if ( '' === $product['identifier'] || 0 >= (int) $product['quantity'] ) {
				// It's all or nothing. If one product is invalid, we don't perform the stock consumption
				return false;
			}

			$products_to_consume[] = array(
				'quantity'          => $product['quantity'],
				$product_identifier => $product['identifier']
			);
		}

		$body = array(
			'note'     => substr( $note, 0, 100 ),
			'officeId' => $office_id,
			'details'  => $products_to_consume
		);

		try {
			$this->make_request( $api_endpoint, 'POST', $body );
		} catch ( \Exception $e ) {
			return false;
		}

		return true;
	}
}
