<?php

namespace Gwsn\HttpRequest;

use Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use Gwsn\CacheUtil\CacheUtil;


/**
 * Class BaseConnector
 *
 * @package Gwsn\HttpRequest
 */
class BaseConnector {


    const RESPONSE_ORIGINAL  = 'original';
    const RESPONSE_TYPE_JSON = 'json';
    const RESPONSE_TYPE_XML  = 'xml';
    const RESPONSE_TYPE_TEXT = 'text';
    const RESPONSE_TYPE_HTML = 'html';

    /**
     * @var ResponseInterface $response
     */
    protected $response = null;

    /**
     * The statuscodes that are allowed to pass through the proxy
     *
     * @url https://developer.mozilla.org/nl/docs/Web/HTTP/Status
     * @var array $validStatusCodes
     */
    private $validStatusCodes = [];

    /**
     * @var string $baseUri
     */
    private $baseUri = null;

    /**
     * @var int $curlTimeout
     */
    private $curlTimeout = 2.0;

    /**
     * @var CacheUtil $cache
     */
    private $cache = null;

    /**
     * Set CacheTime (default: 1 hour)
     *
     * @var int $cacheTime
     */
    private $cacheTime = ( 1 * 60 * 60 );

    /**
     * Contains the Guzzle Client
     *
     * @var Client $curlClient
     */
    private $curlClient = null;

    /**
     * Check if request is proxy or not
     *
     * @var bool $proxy
     */
    protected $proxy = null;


    /**
     * BaseConnector constructor.
     */
    public function __construct() {
        $this->proxy = false;
        $this->setValidStatusCodes( [] );

        $this->cache = new CacheUtil();
    }

    /**
     * @return array
     */
    protected function getValidStatusCodes()
    : array {
        return $this->validStatusCodes;
    }

    /**
     * @param array $validStatusCodes
     *
     * @return $this
     */
    public function setValidStatusCodes( array $validStatusCodes ) {
        $this->validStatusCodes = ( $validStatusCodes !== [] ?
            $validStatusCodes
            :
            [ 200, 201, 202, 204 ]
        );

        return $this;
    }

    /**
     * Return the timout of Curl Connection (default 60 seconds)
     *
     * @return int $curlTimeout
     */
    private function getCurlTimeout() {
        return $this->curlTimeout;
    }

    /**
     * Return the base uri if its set
     *
     * @return string
     */
    protected function getBaseUri() {
        return $this->baseUri;
    }

    /**
     * @param $baseUri
     *
     * @return $this
     */
    public function setBaseUri( $baseUri ) {
        $this->baseUri = $baseUri;

        return $this;
    }

    /**
     * @return int
     */
    protected function getCacheTime()
    : int {
        return $this->cacheTime;
    }

    /**
     * @param int $cacheTime
     */
    public function setCacheTime( int $cacheTime ) {
        $this->cacheTime = $cacheTime;
    }


    /**
     * Prepare the Guzzle Client
     *
     * @param Client|null $client
     */
    public function prepareCall( Client $client = null ) {
        // Set the default client:
        $this->curlClient = null;
        $client           = null;

        Log::info( 'prepare ' . __CLASS__ . ' GuzzleRequest: base_uri(' . $this->getBaseUri() . '), client(' . ( (bool) is_null( $client ) ) . '), timeout(' . $this->getCurlTimeout() . '), ' );

        // Check if baseUri is set or that $client is set (make pass trough client available else set the client self)
        if ( $this->getBaseUri() !== null && $client === null ) {
            // Initialize the Client:
            $this->curlClient = new Client( [
                // Base URI is used with relative requests
                'base_uri' => $this->getBaseUri(),

                // You can set any number of default request options.
                'timeout'  => 2.0,
            ] );
        } else if ( $client !== null ) {
            $this->curlClient = $client;
        }
    }

    /**
     * @param string $method
     * @param string $url
     * @param array  $data
     * @param array  $headers
     *
     * @return bool|mixed
     */
    public function execute( $method, $url, array $data = [], array $headers = [] ) {
        // Define the response
        $this->response = null;
        $response       = null;
        $bodyType       = 'form_params';
        $headers        = $this->sanitizeHeaders($headers);

        // String replace the base url to prevent double baseUri
        $url = str_replace( $this->getBaseUri(), '', $url );

        $cacheKey = hash( 'sha512', $method . $url . md5( json_encode( $data ) ) . md5( json_encode( $headers ) ) );

        if ( 1===3 && $this->cache->has($cacheKey) ) {
            $response = $this->cache->get($cacheKey);
            $this->setResponse( $response );
            $this->response->setCacheHit( true );
            return true;
        }

        // Check headers for Content Type
        if ( isset($headers['content-type']) && $headers['content-type'] === 'application/json' ) {
            $bodyType = 'json';
        }

        Log::info( 'Start ' . __CLASS__ . ' GuzzleRequest: method(' . $method . '), url(' . $url . '), ' );

        try {
            // Do the actual request with Guzzle PSR7
            switch ( strtolower( $method ) ) {
                default:
                case 'get':
                    $response = $this->curlClient->request( strtoupper( $method ), $url, [
                        'headers' => $headers,
                    ] );
                    break;
                case 'put':
                case 'post':
                    $response = $this->curlClient->request( strtoupper( $method ), $url, [
                        $bodyType => $data,
                        'headers' => $headers,
                    ] );
                    break;
            }

            $validResponse = $this->checkValidResponse( $response->getStatusCode() );
        }
        catch ( ClientException $e ) {
            $validResponse = $this->checkValidResponse( $e->getCode() );
            if ( $validResponse === false ) {
                throw new \RuntimeException( 'Client Exception ' . $e->getMessage(), $e->getCode() );
            }
            if ( method_exists( $e, 'getResponse' ) ) {
                $response = $e->getResponse();
            }
        }
        catch ( \Exception $e ) {
            $validResponse = $this->checkValidResponse( $e->getCode() );
            if ( $validResponse === false ) {
                throw new \RuntimeException( 'Exception ' . $e->getMessage(), $e->getCode() );
            }
            if ( method_exists( $e, 'getResponse' ) ) {
                $response = $e->getResponse();
            }
        }

        // Write the response to the cache
        $this->cache->set($cacheKey, $response, $this->getCacheTime());

        // Write down the response
        $this->setResponse( $response );


        // When the response is not valid return a
        if ( $validResponse === false ) {
            // the response is not valid this can be a configuration issue or the API is not responding well.
            // Base line is that we need to return a "500: Internal Server Error" with as a error the original message from the API
            throw new \RuntimeException( 'HTTP exception, got a ' . $response->getStatusCode() . ' status, expect only one of these (' . implode( ',', $this->getValidStatusCodes() ) . ')', $response->getStatusCode() );
        }

        return true;
    }

    /**
     * Write down the response
     *
     * @param Response $response
     */
    protected function setResponse( Response $response ) {
        $this->response = ( new BaseResponse )->setResponse( $response );
    }


    /**
     * Get the response body from the response and try to parse it to array or object.
     *
     * @param string $type
     *
     * @return null|string|BaseResponseInterface
     */
    public function getResponse( $type = self::RESPONSE_TYPE_JSON ) {
        // Check if response is set
        if ( $this->response === null ) {
            return null;
        }

        try {
            // Get the stream from the response getBody() method returns a stream instead of a string.
            $stream = $this->response->getBody();

            // Get the body from the stream.
            $body = $stream->getContents();

            if ( empty( $body ) ) {
                return null;
            }

            switch ( $type ) {
                default:
                case self::RESPONSE_TYPE_JSON:
                    return $this->getResponseJSON( $body );
                    break;
                case self::RESPONSE_TYPE_XML:
                    return (string) $body;
                    break;
                case self::RESPONSE_TYPE_HTML:
                    return (string) $body;
                    break;
                case self::RESPONSE_TYPE_TEXT:
                    return (string) $body;
                    break;
                case self::RESPONSE_ORIGINAL:
                    return $this->response;
                    break;
            }
        }
        catch ( BadResponseException $exception ) {
            return null;
        }
        catch ( \Exception $exception ) {
            return null;
        }
    }

    /**
     * Decode the JSON String
     *
     * @param string $body JSON encoded string
     *
     * @return string
     */
    private function getResponseJSON( $body ) {
        try {
            return json_decode( (string) $body, true );
        }
        catch ( \Exception $e ) {
            throw new \RuntimeException( 'Invalid JSON ' . $e->getMessage(), 0, $body );
        }
    }

    /**
     * @param array $headers
     *
     * @return array
     */
    private function sanitizeHeaders( array $headers = [] ) {
        // Check if headers is set and is not empty
        if ( empty( $headers )) {
            return [];
        }

        foreach($headers as $header => $value) {
            if ( is_array( $value ) && count( $value ) === 1 ) {
                $headers[$header] = array_pop( $value );
            }
        }

        return $headers;
    }

    /**
     * @param int $statusCode
     *
     * @return bool
     */
    protected function checkValidResponse( $statusCode = null ) {
        $valid_status_codes = $this->getValidStatusCodes();
        foreach ( $valid_status_codes as $valid_status_code ) {
            if ( $statusCode === intval( $valid_status_code ) ) {
                return true;
            }
        }

        return false;
    }




}