<?php

namespace Gwsn\HttpRequest;

use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;


class BaseProxyConnector extends BaseConnector {


	private $endpointsConfig;

	private $endpointRequiredKeys = [ 'name', 'endpoint', 'auth', 'auth_type', 'auth_user', 'auth_pass', 'proxy' ];

	public function __construct() {
		parent::__construct();
		$this->proxy = true;

		// Define the default statuscodes
		$this->setValidStatusCodes( [
			                            200,
			                            201,
			                            202,
			                            203,
			                            204,
			                            205,
			                            206,
			                            400,
			                            401,
			                            402,
			                            403,
			                            404,
			                            405,
			                            406,
			                            407,
			                            408,
			                            410,
			                            501,
		                            ] );
	}

	/**
	 * Fetch the endpoints from config.
	 *
	 * @return array
	 *
	 * @throws \Exception
	 */
	protected function getEndpoints() {
		// Get Endpoint Configs

		try {
			$endpoints = ApiEndPoints::getEndpoints();

			return $this->setEndpoints( $endpoints );

		}
		catch ( \Exception $exception ) {
			throw $exception;
		}
	}

	/**
	 * Fetch the endpoints from config.
	 *
	 * @param array $endpoints
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function setEndpoints( array $endpoints = [] ) {
		if ( empty( $endpoints ) ) {
			throw new \Exception( 'Endpoints variable is empty.' );
		}

		foreach ( $endpoints as $endpoint ) {
			// Check if required configurations are set
			foreach ( $this->endpointRequiredKeys as $key ) {
				if ( ! isset( $endpoint[ $key ] ) ) {
					throw new \Exception( 'Endpoint (' . $endpoint . ') is not defined correctly, the config key (' . $key . ') is not defined' );
				}
			}
		}

		$this->endpointsConfig = $endpoints;
	}

	/**
	 * @param null $endpoint
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function getEndpointConfig( $endpoint = null ) {
		if ( $endpoint === null ) {
			throw new \Exception( 'Endpoint is null' );
		}

		if ( ! isset( $this->endpointsConfig[ $endpoint ] ) ) {
			throw new \Exception( 'Endpoint is not defined in the config.' );
		}

		if ( ! is_array( $this->endpointsConfig ) || $this->endpointsConfig === [] ) {
			throw new \Exception( 'There are not Endpoints configurations set, please define the endpoints first.' );
		}


		return $this->endpointsConfig[ $endpoint ];
	}

	/**
	 * Redirect the API call to the micro service
	 *
	 * @param Request $request
	 * @param string  $endpoint
	 * @param string  $path
	 * @param string  $responseType
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function redirect( Request $request, $endpoint, $path, $responseType = 'json' ) {
		// Set the Endpoints
		$this->getEndpoints();

		// Get Endpoint config.
		$this->setBaseUri( $this->getEndpointConfig( $endpoint )['endpoint'] );

		// Get Endpoint AUTH
		// $this->setAuthHeaders();

		// Get Required variables for the endpoint.
		// void

		// Add Proxy to request
		$request->attributes->add( [
			                           'proxy' => [
				                           'host'          => $this->getBaseUri(),
				                           'path'          => $path,
				                           'response_type' => $responseType,
			                           ],
		                           ] );


		// Create a call to the endpoint
		$this->prepareCall( null );

		// Prepare headers:
		$headers = $request->headers->all();
		if ( isset( $headers['host'] ) ) {
			unset( $headers['host'] );
		}

		// Set proxy headers
		$headers['bizhost-request-identifier'] = [ $request->get( 'bizhost-request-identifier' , null) ];
		$headers['bizhost-session-identifier'] = [ $request->get( 'bizhost-session-identifier' , null) ];


		// Prepare data
		$data = $request->all();


		// Execute call
		$this->execute( $request->getMethod(), $path, $data, $headers );

		// Catch the results
		return $this->getResponse( $responseType );
	}

	/**
	 * @param Request $request
	 * @param null    $method
	 * @param null    $endpoint
	 * @param null    $path
	 * @param array   $data
	 * @param array   $headers
	 * @param string  $responseType
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function call( Request $request = null, $method = null, $endpoint = null, $path = null, $data = [], $headers = [], $responseType = 'json' ) {
		// Set the Endpoints
		$this->getEndpoints();

		// Get Endpoint config.
		$this->setBaseUri( $this->getEndpointConfig( $endpoint )['endpoint'] );



		// Add Proxy to request
		$request->attributes->add( [
			                           'proxy' => [
				                           'host'          => $this->getBaseUri(),
				                           'path'          => $path,
				                           'response_type' => $responseType,
			                           ],
		                           ] );


		// Create a call to the endpoint
		$this->prepareCall( null );

		// Prepare headers:
		$headers = array_merge($request->headers->all(), $headers);
		if ( isset( $headers['host'] ) ) {
			unset( $headers['host'] );
		}

		if($responseType === 'json') {
			$headers['content-type'] = 'application/json';
		}
		// Set proxy headers
		$headers['bizhost-request-identifier'] = [ $request->get( 'bizhost-request-identifier' , null) ];
		$headers['bizhost-session-identifier'] = [ $request->get( 'bizhost-session-identifier' , null) ];



		// Execute call
		$this->execute( $method, $path, $data, $headers );

		// Catch the results
		return $this->getResponse( $responseType );
	}


	/**
	 * Set the Guzzle Response
	 *
	 * @param Response $response
	 *
	 * @return $this;
	 */
	protected function setResponse( Response $response ) {
		$this->response = ( new BaseProxyResponse )->setResponse( $response );

		return $this;
	}


}