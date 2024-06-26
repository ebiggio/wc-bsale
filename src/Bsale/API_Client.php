<?php
/**
 * Manages the communication with the Bsale API.
 *
 * @class   API_Client
 * @package WC_Bsale
 */

namespace WC_Bsale\Bsale;

defined( 'ABSPATH' ) || exit;

use Exception;
use WP_Error;

/**
 * API_Client class
 */
class API_Client {
	private string $api_url;
	private string $access_token;
	/**
	 * The type of identifier to use when searching for products in Bsale. Can be 'code' or 'barcode'.
	 * Keep in mind that the identifier is case-sensitive for **some** endpoints in the sandbox environment. For example, the stock retrieval endpoint uses 'barcode' as the identifier, but the stock consumption endpoint uses 'barCode'.
	 *
	 * @var string
	 */
	private string $product_identifier;
	private array|null $bsale_response = null;
	private WP_Error|null $bsale_wp_error = null;

	public function __construct() {
		$this->api_url = 'https://api.bsale.io/v1/';

		$main_settings            = \WC_Bsale\Admin\Settings\Main::get_settings();
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
			$this->bsale_wp_error = new WP_Error( 422, 'The Bsale API access token is not set.' );

			throw new Exception( 'The Bsale API access token is not set.' );
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
					$this->bsale_wp_error = new WP_Error( wp_remote_retrieve_response_code( $bsale_response ), $response_body->error );
				} else {
					$this->bsale_wp_error = new WP_Error( wp_remote_retrieve_response_code( $bsale_response ), wp_remote_retrieve_response_message( $bsale_response ) );
				}
			}

			throw new Exception( 'Error making the request to the Bsale API: ' . $this->bsale_wp_error->get_error_message() );
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
	public function get_last_wp_error(): WP_Error|null {
		return $this->bsale_wp_error;
	}

	/**
	 * Searches for **active** entities in Bsale by their names and returns their IDs and names.
	 *
	 * @param string $name       The name of the entity to search for.
	 * @param string $endpoint   The API endpoint to use in the search for the entities.
	 * @param array  $parameters Additional parameters to query the endpoint, in a key-value format. Each key-value pair will be added to the query string as '&key=value'. Defaults to an empty array.
	 *
	 * @return array The list of entities that matches the name provided. Will be empty if no entities were found or if an empty name was provided.
	 * @throws \Exception If there was an error fetching the list of entities from Bsale.
	 */
	private function search_entities_by_name( string $name, string $endpoint, array $parameters = array() ): array {
		$entities = array();

		if ( '' === $name ) {
			return $entities;
		}

		$name = urlencode( $name );

		$additional_query_parameters = '';

		// Adds any additional parameters to the query string
		if ( ! empty( $parameters ) ) {
			$additional_query_parameters = '&' . http_build_query( $parameters );
		}

		$entities_list = $this->make_request( $endpoint . '?state=0&fields=[name]&name=' . $name . $additional_query_parameters );

		foreach ( $entities_list->items as $entity ) {
			$entities[] = array(
				'id'   => $entity->id,
				'name' => $entity->name
			);
		}

		return $entities;
	}

	/**
	 * Gets an **active** entity from Bsale by its ID.
	 *
	 * @param int    $entity_id The ID of the entity to get.
	 * @param string $endpoint  The API endpoint where the entity is located.
	 *
	 * @return array The entity's data. Will be empty if the entity was not found, an empty ID was provided, or if the entity is not active.
	 * @throws \Exception If there was an error fetching the entity from Bsale.
	 */
	private function get_active_entity_by_id( int $entity_id, string $endpoint ): array {
		if ( 0 === $entity_id ) {
			return array();
		}

		try {
			$entity = $this->make_request( $endpoint . $entity_id . '.json' );
		} catch ( Exception $e ) {
			// If the error code is 404, it means that the entity was not found. That's a valid case, so we return an empty array. In any other situation, we throw the exception
			if ( 404 !== $this->bsale_wp_error->get_error_code() ) {
				throw $e;
			}

			return array();
		}

		// Check the entity's state. If it's not active, we return an empty array. In Bsale, a state of 0 means that the entity is active (yes, it's counterintuitive)
		if ( 0 !== $entity->state ) {
			return array();
		}

		return (array) $entity;
	}

	/**
	 * Retrieves a product's **available** stock by its identifier from a specific office in Bsale.
	 *
	 * @param $identifier string The product's identifier. Can be the product's code or barcode.
	 * @param $office_id  int The ID of the office to get the stock from.
	 *
	 * @return int|bool The product's stock, or false if an empty identifier or office ID was provided or if no stock was found in Bsale.
	 * @throws \Exception If there was an error fetching the stock from Bsale.
	 *
	 * @link https://docs.bsale.dev/CL/stocks#get-un-stock-de-variante Bsale API documentation for the stock retrieval endpoint.
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
	 * Consumes stock of products in Bsale.
	 *
	 * @param string $note      A description of the stock consumption. Will be displayed in Bsale's interface. Max length will be set to 100 characters.
	 * @param int    $office_id The ID of the office to consume the stock from.
	 * @param array  $products  An array of products to consume the stock from. Each product must have an SKU and a 'quantity' key, and the 'quantity' must be greater than 0.
	 *
	 * @return bool True if the stock was consumed successfully for all the products. False if an empty note or office ID was provided, or if there was an error consuming the stock.
	 *
	 * @link https://docs.bsale.dev/CL/stocks#post-un-consumo-de-stock Bsale API documentation for the stock consumption endpoint.
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
		} catch ( Exception ) {
			return false;
		}

		return true;
	}

	/**
	 * Generates a document in Bsale. The type of document to generate will depend on the data provided.
	 *
	 * @param array $document_data The data of the document to generate. The data must be in the format expected by the Bsale API.
	 *
	 * @return array The response data from Bsale. Will be empty if there was an error generating the document.
	 *
	 * @link https://docs.bsale.dev/CL/documentos/#post-un-documento Bsale API documentation for the document generation endpoint.
	 */
	public function generate_document( array $document_data ): array {
		$api_endpoint = 'documents.json';

		try {
			$bsale_response = $this->make_request( $api_endpoint, 'POST', $document_data );
		} catch ( Exception ) {
			return array();
		}

		return (array) $bsale_response;
	}

	/**
	 * Wrapper function to generate an invoice in Bsale.
	 *
	 * Will remove the 'officeId' and 'priceListId' keys from the data if they are set to 0, making Bsale use the default values of the connected account when generating the invoice.
	 *
	 * @param array $invoice_data The data of the invoice to generate.
	 *
	 * @return array The response data from Bsale. Will be empty if there was an error generating the invoice.
	 */
	public function generate_invoice( array $invoice_data ): array {
		// Check if the office ID is set. If it's not, remove it from the data
		if ( 0 === $invoice_data['officeId'] ) {
			unset( $invoice_data['officeId'] );
		}

		// Check if the price list ID is set. If it's not, remove it from the data
		if ( 0 === $invoice_data['priceListId'] ) {
			unset( $invoice_data['priceListId'] );
		}

		// Format the tax IDs if they are set, and replace the identifier key with the configured product identifier (code or barcode)
		foreach ( $invoice_data['details'] as $key => $item ) {
			// Check if there are any taxes set for the item. If there are, we need to format them as Bsale expects them (a string surrounded by square brackets)
			if ( isset( $item['taxId'] ) ) {
				$invoice_data['details'][ $key ]['taxId'] = '[' . implode( ',', $item['taxId'] ) . ']';
			}

			// Check if an identifier is set. If it's not, we don't need to do anything, since it means that the product is not in Bsale and the item must be declared "as is"
			if ( ! isset( $item['identifier'] ) ) {
				continue;
			}

			// TODO Check if the product identifier is case-sensitive in the production environment
			$product_identifier = 'barcode' === $this->product_identifier ? 'barCode' : 'code';

			$invoice_data['details'][ $key ][ $product_identifier ] = $item['identifier'];
			unset( $invoice_data['details'][ $key ]['identifier'] );
		}

		return $this->generate_document( $invoice_data );
	}

	/**
	 * Searches for a variant in Bsale by an identifier (code or barcode) and return its data.
	 *
	 * Will only return the first variant found (if any) even if there are multiple variants with the same identifier (which shouldn't happen, since the identifier should be unique).
	 * This method won't filter a variant by its state (active or inactive), so it's possible to get an inactive variant.
	 *
	 * @param string $identifier The variant's identifier. Can be a variant's code or barcode.
	 *
	 * @return array The variant's data. Will be empty if no variant was found for the identifier provided.
	 * @throws \Exception If there was an error fetching the variant from Bsale.
	 *
	 * @link https://docs.bsale.dev/CL/variantes/#get-lista-de-variantes Bsale API documentation for listing variants endpoint.
	 */
	public function get_variant_by_identifier( string $identifier ): array {
		if ( '' === $identifier ) {
			return array();
		}

		$variant = $this->make_request( 'variants.json?' . $this->product_identifier . '=' . $identifier );

		if ( 0 === $variant->count ) {
			// No variant found for the identifier provided
			return array();
		}

		return (array) $variant->items[0];
	}

	/**
	 * Gets the price (with taxes) of a variant from a specific price list in Bsale.
	 *
	 * @param int    $price_list_id The ID of the price list to get the price from.
	 * @param string $identifier    The variant's identifier. Can be a variant's code or barcode.
	 *
	 * @return float|bool The variant's price from the price list. Will be false if an empty price list ID or identifier was provided, or if no price was found for the provided identifier.
	 * @throws \Exception If there was an error fetching the price from Bsale.
	 *
	 * @link https://docs.bsale.dev/CL/listas-de-precio#get-detalles-de-una-lista-de-precio Bsale API documentation for the price list details endpoint.
	 */
	public function get_variant_price_from_price_list( int $price_list_id, string $identifier ): float|bool {
		if ( 0 === $price_list_id || '' === $identifier ) {
			return false;
		}

		$price_details = $this->make_request( 'price_lists/' . $price_list_id . '/details.json?' . $this->product_identifier . '=' . $identifier );

		if ( 0 === $price_details->count ) {
			// No price found for the variant in the price list provided
			return false;
		}

		return (float) $price_details->items[0]->variantValueWithTaxes;
	}

	/**
	 * Searches for **active** offices in Bsale by their names and returns their IDs and names.
	 *
	 * @param string $name The name of the office to search for.
	 *
	 * @return array The list of offices that matches the name provided. Will be empty if no offices were found or if an empty name was provided.
	 * @throws \Exception If there was an error fetching the list of offices from Bsale.
	 *
	 * @see  \WC_Bsale\Bsale\API_Client::search_entities_by_name() For the implementation of the search.
	 * @link https://docs.bsale.dev/CL/sucursales#get-lista-de-sucursales Bsale API documentation for listing offices endpoint.
	 */
	public function search_offices_by_name( string $name ): array {
		return $this->search_entities_by_name( $name, 'offices.json' );
	}

	/**
	 * Gets an **active** office from Bsale by its ID.
	 *
	 * @param int $office_id The ID of the office to get.
	 *
	 * @return array The office's data. Will be empty if the office was not found, an empty ID was provided, or if the office is not active.
	 * @throws \Exception If there was an error fetching the office from Bsale.
	 *
	 * @see  \WC_Bsale\Bsale\API_Client::get_active_entity_by_id() For the implementation of how the office is fetched.
	 * @link https://docs.bsale.dev/CL/sucursales#get-una-sucursal Bsale API documentation for the office retrieval endpoint.
	 */
	public function get_office_by_id( int $office_id ): array {
		return $this->get_active_entity_by_id( $office_id, 'offices/' );
	}

	/**
	 * Searches for **active** electronic invoice document types in Bsale by their names and returns their IDs and names.
	 *
	 * The 'codesii' parameter is set to '39' to search for electronic invoice document types only.
	 * This code is according to the SII (Servicio de Impuestos Internos), where 39 corresponds to electronic invoices.
	 *
	 * @param string $name The name of the document type to search for.
	 *
	 * @return array The list of document types that matches the name provided. Will be empty if no document types were found or if an empty name was provided.
	 * @throws \Exception If there was an error fetching the list of document types from Bsale.
	 *
	 * @see  \WC_Bsale\Bsale\API_Client::search_entities_by_name() For the implementation of the search.
	 * @link https://docs.bsale.dev/CL/tipos-de-documentos#get-lista-de-tipos-de-documentos Bsale API documentation for listing document types endpoint.
	 * @link https://www.sii.cl/factura_electronica/formato_dte.pdf SII codes for electronic documents - Version 2.2 2019-07-10, page 41.
	 */
	public function search_invoice_document_types_by_name( string $name ): array {
		return $this->search_entities_by_name( $name, 'document_types.json', array( 'codesii' => '39' ) );
	}

	/**
	 * Gets the details of an **active** document type by its ID.
	 *
	 * @param int $document_type_id The ID of the document type to get.
	 *
	 * @return array The document type's data. Will be empty if the document type was not found, an empty ID was provided, or if the document type is not active.
	 * @throws \Exception If there was an error fetching the document type from Bsale.
	 *
	 * @see  \WC_Bsale\Bsale\API_Client::get_active_entity_by_id() For the implementation of how the document type is fetched.
	 * @link https://docs.bsale.dev/CL/tipos-de-documentos#get-un-tipo-de-documento Bsale API documentation for the document type retrieval endpoint.
	 */
	public function get_document_type_by_id( int $document_type_id ): array {
		return $this->get_active_entity_by_id( $document_type_id, 'document_types/' );
	}

	/**
	 * Searches for **active** price lists in Bsale by their names and returns their IDs and names.
	 *
	 * @param string $name The name of the price list to search for.
	 *
	 * @return array The list of price lists that matches the name provided. Will be empty if no price lists were found or if an empty name was provided.
	 * @throws \Exception If there was an error fetching the list of price lists from Bsale.
	 *
	 * @see  \WC_Bsale\Bsale\API_Client::search_entities_by_name() For the implementation of the search.
	 * @link https://docs.bsale.dev/CL/listas-de-precio#get-listas-de-precio Bsale API documentation for listing price lists endpoint.
	 */
	public function search_price_lists_by_name( string $name ): array {
		return $this->search_entities_by_name( $name, 'price_lists.json' );
	}

	/**
	 * Gets the details of an **active** price list by its ID.
	 *
	 * @param int $price_list_id The ID of the price list to get.
	 *
	 * @return array The price list's data. Will be empty if the price list was not found, an empty ID was provided, or if the price list is not active.
	 * @throws \Exception If there was an error fetching the price list from Bsale.
	 *
	 * @see  \WC_Bsale\Bsale\API_Client::get_active_entity_by_id() For the implementation of how the price list is fetched.
	 * @link https://docs.bsale.dev/CL/listas-de-precio#get-una-lista-de-precio Bsale API documentation for the price list retrieval endpoint.
	 */
	public function get_price_list_by_id( int $price_list_id ): array {
		return $this->get_active_entity_by_id( $price_list_id, 'price_lists/' );
	}

	/**
	 * Searches for **active** taxes in Bsale by their names and returns their IDs and names.
	 *
	 * @param string $name The name of the tax to search for.
	 *
	 * @return array The list of taxes that matches the name provided. Will be empty if no taxes were found or if an empty name was provided.
	 * @throws \Exception If there was an error fetching the list of taxes from Bsale.
	 *
	 * @see  \WC_Bsale\Bsale\API_Client::search_entities_by_name() For the implementation of the search.
	 * @link https://docs.bsale.dev/CL/impuestos#get-lista-de-impuestos Bsale API documentation for listing taxes endpoint.
	 */
	public function search_taxes_by_name( string $name ): array {
		return $this->search_entities_by_name( $name, 'taxes.json' );
	}

	/**
	 * Gets the details of an **active** tax by its ID.
	 *
	 * @param int $tax_id The ID of the tax to get.
	 *
	 * @return array The tax's data. Will be empty if the tax was not found, an empty ID was provided, or if the tax is not active.
	 * @throws \Exception If there was an error fetching the tax from Bsale.
	 *
	 * @see  \WC_Bsale\Bsale\API_Client::get_active_entity_by_id() For the implementation of how the tax is fetched.
	 * @link https://docs.bsale.dev/CL/impuestos#get-un-impuesto Bsale API documentation for the tax retrieval endpoint.
	 */
	public function get_tax_by_id( int $tax_id ): array {
		return $this->get_active_entity_by_id( $tax_id, 'taxes/' );
	}
}
