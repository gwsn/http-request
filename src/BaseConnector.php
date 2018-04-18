<?php

namespace Gwsn\HttpRequest;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;



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
    private $validStatusCodes = [ 200, 201, 202, 204 ];

    /**
     * @var string $baseUri
     */
    private $baseUri = null;

    /**
     * @var int $curlTimeout
     */
    private $curlTimeout = 2.0;

    /**
     * @var object $cacheUtil
     */
    private $cacheUtil = null;

    /**
     * @var LoggerInterface $logger
     */
    private $logger = null;

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
     *
     * @param $cacheUtil
     * @param $logger
     */
    public function __construct(LoggerInterface $logger = null, $cacheUtil = null) {
        $this->proxy = false;
        $this->cacheUtil = $cacheUtil;
        $this->logger = $logger;
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
        if(empty($validStatusCodes)) {
            throw new \InvalidArgumentException('Cannot set empty valid status codes, please fill in at least one');
        }
        $this->validStatusCodes = $validStatusCodes;

        return $this;
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
     * Return the timout of Curl Connection (default 60 seconds)
     *
     * @return int $curlTimeout
     */
    private function getCurlTimeout(): int
    {
        return $this->curlTimeout;
    }

    /**
     * @param int $curlTimeout
     */
    public function setCurlTimeout(int $curlTimeout): void
    {
        $this->curlTimeout = $curlTimeout;
    }

    /**
     * @return object
     */
    public function getCacheUtil()
    {
        return $this->cacheUtil;
    }

    /**
     * @param object $cacheUtil
     */
    public function setCacheUtil($cacheUtil): void
    {
        $this->cacheUtil = $cacheUtil;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return Client
     */
    public function getCurlClient(): Client
    {
        return $this->curlClient;
    }

    /**
     * @param Client $curlClient
     */
    public function setCurlClient(Client $curlClient): void
    {
        $this->curlClient = $curlClient;
    }

    /**
     * @return bool
     */
    public function isProxy(): bool
    {
        return $this->proxy;
    }

    /**
     * @param bool $proxy
     */
    public function setProxy(bool $proxy): void
    {
        $this->proxy = $proxy;
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

        $this->log( 'prepare ' . __CLASS__ . ' GuzzleRequest: base_uri(' . $this->getBaseUri() . '), client(' . ( (bool) is_null( $client ) ) . '), timeout(' . $this->getCurlTimeout() . '), ', 'debug');

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

        if ($this->hasCache($cacheKey)) {
            $response = $this->getCache($cacheKey);
            $this->setResponse( $response );
            $this->response->setCacheHit( true );
            return true;
        }

        // Check headers for Content Type
        if ( isset($headers['content-type']) && $headers['content-type'] === 'application/json' ) {
            $bodyType = 'json';
        }

        $this->log( 'Start ' . __CLASS__ . ' GuzzleRequest: method(' . $method . '), url(' . $url . '), ' , 'debug');


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
                $this->log( $e->getFile().':'.$e->getLine().' Client Exception ' . $e->getMessage().' - '.$e->getCode() , 'error');
                throw new \RuntimeException( 'Client Exception ' . $e->getMessage(), $e->getCode() );
            }
            if ( method_exists( $e, 'getResponse' ) ) {
                $response = $e->getResponse();
            }
        }
        catch ( \Exception $e ) {
            $validResponse = $this->checkValidResponse( $e->getCode() );
            if ( $validResponse === false ) {
                $this->log( $e->getFile().':'.$e->getLine().' Client Exception ' . $e->getMessage().' - '.$e->getCode() , 'error');
                throw new \RuntimeException( 'Exception ' . $e->getMessage(), $e->getCode() );
            }
            if ( method_exists( $e, 'getResponse' ) ) {
                $response = $e->getResponse();
            }
        }

        // Write the response to the cache
        $this->setCache($cacheKey, $response, $this->getCacheTime());

        // Write down the response
        $this->setResponse( $response );


        // When the response is not valid return a
        if ( $validResponse === false ) {
            // the response is not valid this can be a configuration issue or the API is not responding well.
            // Base line is that we need to return a "500: Internal Server Error" with as a error the original message from the API
            $this->log( 'HTTP exception, got a ' . $response->getStatusCode() . ' status, expect only one of these (' . implode( ',', $this->getValidStatusCodes() ) . ')', 'error');
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


    /**
     * @param string $key
     *
     * @return bool
     */
    protected function hasCache(string $key) {
        $cacheUtil = $this->getCacheUtil();

        if($cacheUtil === null) {
            return false;
        }
        return $this->cacheUtil->has($key);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    protected function getCache(string $key) {
        $cacheUtil = $this->getCacheUtil();

        if($cacheUtil === null) {
            return null;
        }

        return $this->cacheUtil->get($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int|null $cacheTime
     *
     * @return bool|null
     */
    protected function setCache(string $key, $value = null, int $cacheTime = null) {
        $cacheTime = ($cacheTime === null ? $this->getCacheTime() : $cacheTime);
        $cacheUtil = $this->getCacheUtil();

        if($cacheUtil === null) {
            return null;
        }

        return $this->cacheUtil->set($key, $value, $cacheTime);
    }

    /**
     * Logging the message to a log when logger is available else write it to /dev/null
     * @param string $message
     * @param string $type
     *
     * @return bool
     */
    protected function log(string $message = 'Logging - void', string $type = 'info'):bool
    {
        if(empty($this->logger)) {
            return false;
        }

        switch ($type) {
            case 'emergency':
                $this->logger->emergency($message);
                break;
            case 'alert':
                $this->logger->alert($message);
                break;
            case 'critical':
                $this->logger->critical($message);
                break;
            case 'error':
                $this->logger->error($message);
                break;
            case 'warning':
                $this->logger->warning($message);
                break;
            case 'notice':
                $this->logger->notice($message);
                break;
            default:
            case 'info':
                $this->logger->info($message);
                break;
            case 'debug':
                $this->logger->debug($message);
                break;
        }
        return true;
    }



}