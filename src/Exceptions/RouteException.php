<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\MedoolsRouter\Exceptions;

use aryelgois\MedoolsRouter\Response;

/**
 * Error detected when processing a route
 *
 * @author Aryel Mota Góis
 * @license MIT
 * @link https://www.github.com/aryelgois/medools-router
 */
class RouteException extends \Exception
{
    /**
     * Response stored
     *
     * @var Response
     */
    protected $response;

    /**
     * Creates a new RouteException object
     *
     * @param Response  $response Response with error message
     * @param Throwable $previous Previous exception for chaining
     */
    public function __construct(
        Response $response,
        Throwable $previous = null
    ) {
        $this->response = $response;

        parent::__construct($response->body['message'], 0, $previous);
    }

    /**
     * Returns stored response
     *
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
}
