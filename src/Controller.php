<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\MedoolsRouter;

use aryelgois\Utils\HttpResponse;
use aryelgois\MedoolsRouter\Exceptions\RouterException;

/**
 * Simplifies Router usage
 *
 * @author Aryel Mota Góis
 * @license MIT
 * @link https://www.github.com/aryelgois/medools-router
 */
class Controller
{
    /**
     * Router instance
     *
     * @var Router
     */
    protected $router;

    /**
     * Creates a new Controller object
     *
     * If an error occurs, it is outputted
     *
     * @param string $url          Router URL
     * @param array  $resources    List of resources available
     * @param array  $config       Configurations for the Router
     *                             @see Router::CONFIGURABLE
     * @param string $router_class Router class to be used
     *
     * @throws \LogicException If $router_class is not subclass of Router
     */
    public function __construct(
        string $url,
        array $resources,
        array $config = null,
        string $router_class = null
    ) {
        if ($router_class === null) {
            $router_class = Router::class;
        } elseif (!is_subclass_of($router_class, Router::class)) {
            $message = $router_class . ' is not subclass of ' . Router::class;
            throw new \LogicException($message);
        }

        try {
            $this->router = new $router_class($url, $resources, $config);
        } catch (RouterException $e) {
            $e->getResponse()->output();
        }
    }

    /**
     * Authenticates a Basic Authorization Header
     *
     * When successful, a JWT is sent. It must be used for Bearer Authentication
     * with other routes
     *
     * If the authentication is disabled, a 204 response is sent
     *
     * @param string $auth Request Authorization Header
     */
    public function authenticate(string $auth)
    {
        if ($this->router === null) {
            return;
        }

        try {
            $response = $this->router->authenticate($auth, 'Basic');
            if (!($response instanceof Response)) {
                $response = new Response;
                $response->status = HttpResponse::HTTP_NO_CONTENT;
            }
            $response->output();
        } catch (RouterException $e) {
            $e->getResponse()->output();
        }
    }

    /**
     * Runs the Router and outputs the Response
     *
     * @param string $method  Requested HTTP method
     * @param string $uri     Requested URI
     * @param array  $headers Request Headers
     * @param string $body    Request Body
     */
    public function run(
        string $method,
        string $uri,
        array $headers,
        string $body
    ) {
        if ($this->router === null) {
            return;
        }

        try {
            $response = $this->router->run($method, $uri, $headers, $body);
            if ($response !== null) {
                $response->output();
            }
        } catch (RouterException $e) {
            $e->getResponse()->output();
        }
    }
}
