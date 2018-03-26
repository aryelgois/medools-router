<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\MedoolsRouter;

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
     * @param string $url       Router URL
     * @param array  $resources List of resources available
     * @param array  $config    Configurations for the Router
     *                          @see Router::CONFIGURABLE
     */
    public function __construct(
        string $url,
        array $resources,
        array $config = null
    ) {
        try {
            $this->router = new Router($url, $resources, $config);
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

        if (strcasecmp($method, 'POST') === 0) {
            $actual_method = $headers['X-Http-Method-Override'] ?? 'POST';
        }
        $actual_method = strtoupper($actual_method ?? $method);

        try {
            $response = $this->router->run(
                $actual_method,
                $uri,
                $headers,
                $body
            );
            if ($response !== null) {
                $response->output();
            }
        } catch (RouterException $e) {
            $e->getResponse()->output();
        }
    }
}
