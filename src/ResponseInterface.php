<?php
namespace Gwsn\HttpRequest;


use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\StreamInterface;

/**
 * Interface BaseResponseInterface
 *
 * @package Gwsn\HttpRequest\BaseConnector
 */
interface ResponseInterface {

    /**
     * @param Response|null $response
     *
     * @return ResponseInterface
     */
    public function setResponse(Response $response = null);

    /**
     * @return bool
     */
    public function getCacheHit();

    /**
     * @param bool $cacheHit
     *
     * @return void
     */
    public function setCacheHit($cacheHit = false);

    /**
     * @return mixed
     */
    public function getReasonPhrase();

    /**
     * @return mixed
     */
    public function getProtocolVersion();

    /**
     * @param null $version
     *
     * @return mixed
     */
    public function withProtocolVersion($version = null);

    /**
     * @return mixed
     */
    public function getHeaders();

    /**
     * @param null $header
     *
     * @return mixed
     */
    public function getHeader($header = null);

    /**
     * @param null $header
     *
     * @return mixed
     */
    public function getHeaderLine($header = null);

    /**
     * @param null $header
     *
     * @return mixed
     */
    public function hasHeader($header = null);

    /**
     * @param null $header
     * @param null $value
     *
     * @return mixed
     */
    public function withHeader($header = null, $value = null);

    /**
     * @param null $header
     * @param null $value
     *
     * @return mixed
     */
    public function withAddedHeader($header = null, $value = null);

    /**
     * @param null $header
     *
     * @return mixed
     */
    public function withoutHeader($header = null);

    /** @return int */
    public function getStatusCode();

    /**
     * @param null $code
     * @param null $reasonPhrase
     *
     * @return mixed
     */
    public function withStatus($code = null, $reasonPhrase = null);


    /**
     * @return mixed
     */
    public function getBody();

    /**
     * @param StreamInterface $body
     *
     * @return mixed
     */
    public function withBody(StreamInterface $body);

}