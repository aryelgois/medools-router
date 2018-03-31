<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\MedoolsRouter;

use aryelgois\Utils\Utils;
use aryelgois\Utils\HttpResponse;

/**
 * Holds data processed from a route
 *
 * @author Aryel Mota Góis
 * @license MIT
 * @link https://www.github.com/aryelgois/medools-router
 */
class Response
{
    /**
     * Content body to be echoed
     *
     * @var mixed
     */
    public $body;

    /**
     * HTTP headers
     *
     * @var string[]
     */
    public $headers = [];

    /**
     * Requested method
     *
     * @var string
     */
    public $method;

    /**
     * HTTP Status code
     *
     * @var int
     */
    public $status;

    /**
     * If should enable zlib compression when appropriate
     *
     * Only if $method is not HEAD and response can have body
     *
     * @var boolean
     */
    public $zlib_compression = true;

    /**
     * Outputs Response content
     *
     * @throws \Exception If some data has already been output
     *                    @see Utils::checkOutput()
     */
    public function output()
    {
        if ($this->status === null) {
            $this->status = (empty($this->body) || $this->method === 'HEAD')
                ? HttpResponse::HTTP_NO_CONTENT
                : HttpResponse::HTTP_OK;
        }

        if ($this->method === 'HEAD'
            || !HttpResponse::canHaveBody($this->status)
        ) {
            $content_type = 'HEAD';
        } elseif (is_array($this->body)) {
            $content_type = 'application/json';
        } else {
            $content_type = $this->headers['Content-Type'] ?? null;
        }

        Utils::checkOutput($content_type);

        if ($this->zlib_compression && $content_type !== 'HEAD') {
            ini_set('zlib.output_compression', 1);
        }

        header(HttpResponse::getHeader($this->status));
        foreach ($this->headers as $key => $value) {
            header("$key: " . implode(',', (array) $value));
        }

        if ($content_type !== 'HEAD') {
            if (is_array($this->body)) {
                echo json_encode($this->body, JSON_PRETTY_PRINT);
            } else {
                echo $this->body;
            }
        }
    }
}
