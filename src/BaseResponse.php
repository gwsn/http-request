<?php
namespace Gwsn\HttpRequest;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\StreamInterface;

/**
 * Class BaseResponse
 *
 * @package Gwsn\HttpRequest\BaseConnector
 */
class BaseResponse implements ResponseInterface {


    /** @var  Response $response */
    private $response;

    /** @var bool $cacheHit */
    protected $cacheHit = false;


    /**
     * @param Response|null $response
     *
     * @return ResponseInterface
     */
    public function setResponse( Response $response = null ) {
        $this->response = $response;

        // Catch some extra fields an set this as functions to this response.
        return $this;
    }

    /**
     * Check if response is set
     *
     * @throws \Exception
     */
    protected function validateResponse() {
        if($this->response === null)
            throw new \Exception('Response is not set', 1);
    }

    /**
     * @return bool
     */
    public function getCacheHit() {
        return $this->cacheHit;
    }

    /**
     * @param bool $cacheHit
     */
    public function setCacheHit($cacheHit = false) {
        $this->cacheHit = $cacheHit;
    }


    /**
     * @return string
     * @throws \Exception
     */
    public function getReasonPhrase() {
        $this->validateResponse();
        return $this->response->getReasonPhrase();
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getProtocolVersion() {
        $this->validateResponse();
        return $this->response->getProtocolVersion();
    }

    /**
     * @param null $version
     *
     * @return $this|\GuzzleHttp\Psr7\MessageTrait
     * @throws \Exception
     */
    public function withProtocolVersion($version = null) {
        $this->validateResponse();
        return $this->response->withProtocolVersion($version);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getHeaders() {
        $this->validateResponse();
        return $this->response->getHeaders();
    }

    /**
     * @param null $header
     *
     * @return array
     * @throws \Exception
     */
    public function getHeader($header = null) {
        $this->validateResponse();
        return $this->response->getHeader($header);
    }

    /**
     * @param null $header
     *
     * @return string
     * @throws \Exception
     */
    public function getHeaderLine($header = null) {
        $this->validateResponse();
        return $this->response->getHeaderLine($header);
    }

    /**
     * @param null $header
     *
     * @return bool
     * @throws \Exception
     */
    public function hasHeader($header = null) {
        $this->validateResponse();
        return $this->response->hasHeader($header);
    }

    /**
     * @param null $header
     * @param null $value
     *
     * @return \GuzzleHttp\Psr7\MessageTrait
     * @throws \Exception
     */
    public function withHeader($header = null, $value = null) {
        $this->validateResponse();
        return $this->response->withHeader($header, $value);
    }

    /**
     * @param null $header
     * @param null $value
     *
     * @return \GuzzleHttp\Psr7\MessageTrait
     * @throws \Exception
     */
    public function withAddedHeader($header = null, $value = null) {
        $this->validateResponse();
        return $this->response->withAddedHeader($header, $value);
    }

    /**
     * @param null $header
     *
     * @return $this|\GuzzleHttp\Psr7\MessageTrait
     * @throws \Exception
     */
    public function withoutHeader($header = null) {
        $this->validateResponse();
        return $this->response->withoutHeader($header);
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function getStatusCode() {
        $this->validateResponse();
        return $this->response->getStatusCode();
    }

    /**
     * @param null $code
     * @param null $reasonPhrase
     *
     * @return Response|static
     * @throws \Exception
     */
    public function withStatus($code = null, $reasonPhrase = null) {
        $this->validateResponse();
        return $this->response->withStatus($code, $reasonPhrase);
    }

    /**
     * @return \GuzzleHttp\Psr7\Stream|StreamInterface
     * @throws \Exception
     */
    public function getBody() {
        $this->validateResponse();
        return $this->response->getBody();
    }

    /**
     * @param StreamInterface $body
     *
     * @return $this|\GuzzleHttp\Psr7\MessageTrait
     * @throws \Exception
     */
    public function withBody(StreamInterface $body) {
        $this->validateResponse();
        return $this->response->withBody($body);
    }



}