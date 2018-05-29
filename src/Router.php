<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\MedoolsRouter;

use aryelgois\Utils\Utils;
use aryelgois\Utils\Format;
use aryelgois\Utils\HttpResponse;
use aryelgois\Medools\Model;
use aryelgois\Medools\Exceptions\{
    ForeignConstraintException,
    MissingColumnException,
    ReadOnlyModelException,
    UnknownColumnException
};
use aryelgois\MedoolsRouter\Exceptions\RouterException;
use aryelgois\MedoolsRouter\Models\Authentication;
use aryelgois\MedoolsRouter\Models\Authorization;
use Firebase\JWT\JWT;

/**
 * A Router class to bootstrap RESTful APIs based on aryelgois/medools
 *
 * @author Aryel Mota Góis
 * @license MIT
 * @link https://www.github.com/aryelgois/medools-router
 */
class Router
{
    /*
     * Errors
     * =========================================================================
     */

    const ERROR_UNKNOWN_ERROR = 0;
    const ERROR_INTERNAL_SERVER = 1;
    const ERROR_INVALID_CREDENTIALS = 2;
    const ERROR_UNAUTHENTICATED = 3;
    const ERROR_INVALID_TOKEN = 4;
    const ERROR_METHOD_NOT_IMPLEMENTED = 5;
    const ERROR_UNAUTHORIZED = 6;
    const ERROR_INVALID_RESOURCE = 7;
    const ERROR_INVALID_RESOURCE_ID = 8;
    const ERROR_INVALID_RESOURCE_OFFSET = 9;
    const ERROR_INVALID_RESOURCE_FOREIGN = 10;
    const ERROR_RESOURCE_NOT_FOUND = 11;
    const ERROR_UNSUPPORTED_MEDIA_TYPE = 12;
    const ERROR_INVALID_PAYLOAD = 13;
    const ERROR_METHOD_NOT_ALLOWED = 14;
    const ERROR_NOT_ACCEPTABLE = 15;
    const ERROR_INVALID_QUERY_PARAMETER = 16;
    const ERROR_UNKNOWN_FIELDS = 17;
    const ERROR_READONLY_RESOURCE = 18;
    const ERROR_FOREIGN_CONSTRAINT = 19;

    /*
     * Router configurations
     * =========================================================================
     */

    /**
     * List of configurable properties and their types
     *
     * @const string[]
     */
    const CONFIGURABLE = [
        'always_cache'          => 'boolean',
        'always_expand'         => 'boolean',
        'authentication'        => 'array',
        'cache_method'          => 'string',
        'default_content_type'  => 'array',
        'default_filters'       => ['array', 'string'],
        'default_publicity'     => ['boolean', 'array', 'string'],
        'extensions'            => 'array',
        'implemented_methods'   => 'array',
        'meta'                  => ['array', 'NULL'],
        'per_page'              => 'integer',
        'primary_key_separator' => 'string',
        'zlib_compression'      => 'boolean',
    ];

    /**
     * Maps filter operators in query parameters to their Medoo counterpart
     *
     * NOTE:
     * - 'bw' and 'nw' accept two values separated by ','
     * - 'ne', 'lk' and 'nk' accept one or more values separated by ','
     * - They also accept their multiple values in array query parameters
     *
     * @const mixed[]
     */
    const FITLER_OPERATORS = [
        'gt' => '>',      // Greater Than
        'ge' => '>=',     // Greater or Equal to
        'lt' => '<',      // Lesser Than
        'le' => '<=',     // Lesser or Equal to
        'ne' => '!',      // Not Equal
        'bw' => '<>',     // BetWeen
        'nw' => '><',     // Not betWeen
        'lk' => '~',      // LiKe
        'nk' => '!~',     // Not liKe
        'rx' => 'REGEXP', // RegeXp
    ];

    /**
     * List of safe HTTP methods
     *
     * NOTE:
     * - OPTIONS would be here, but it is treated as a special method
     *
     * @const string[]
     */
    const SAFE_METHODS = [
        'GET',
        'HEAD',
    ];

    /**
     * If GET and HEAD requests always check cache headers
     *
     * @var boolean
     */
    protected $always_cache = true;

    /**
     * If foreign models are always expanded in a resource request
     *
     * @var boolean
     */
    protected $always_expand = false;

    /**
     * Contains options for authenticating the request
     *
     * If null, the authentication is disabled
     *
     * If set, the array contains the keys: (only 'secret' is required)
     * - 'secret'     string  Path to secret used to sign the JWT tokens
     * - 'realm'      string  Sent in WWW-Authenticate Header
     * - 'algorithm'  string  Hash algorithm (default: 'HS256')
     * - 'claims'     mixed[] Static JWT claims to be included in every token
     * - 'expiration' int     Expiration duration (seconds)
     * - 'verify'     boolean If the authentication's email must be verified
     *
     * @var mixed[]|null
     */
    protected $authentication;

    /**
     * Function used to hash the Response Body for HTTP Caching
     *
     * It receives a string with the body serialized
     *
     * NOTE:
     * - Caching is only done for GET and HEAD Request methods
     *
     * @var string
     */
    protected $cache_method = 'md5';

    /**
     * List of default content types
     *
     * @var mixed[]
     */
    protected $default_content_type = [
        'application/json' => [
            'handler' => null,
            'priority' => 1,
        ],
    ];

    /**
     * Default value for resources filters
     *
     * @var string|string[]|null
     */
    protected $default_filters;

    /**
     * Default value for 'public' key in $resources
     *
     * @var false    All resources are private by default. Only has effect if
     *               $authentication is not null
     * @var true     All resources are public by default. Has the same effect as
     *               $authentication = null. If it is not null, is useful to
     *               make most resources public and some private
     * @var string[] List of methods that are always public
     * @var string   The same as an array with one item
     */
    protected $default_publicity = false;

    /**
     * Map of known extensions and their related content type
     *
     * Useful to ensure browser explorability via address bar
     *
     * Fill it with extensions for custom content types your resources use
     *
     * @var string[]
     */
    protected $extensions = [];

    /**
     * List of HTTP methods implemented
     *
     * @var string[]
     */
    protected $implemented_methods = [
        'DELETE',
        'GET',
        'HEAD',
        'OPTIONS',
        'PATCH',
        'POST',
        'PUT',
    ];

    /**
     * Information about your Router and API
     *
     * Returned when requesting route '/'
     *
     * @var mixed[]|null
     */
    protected $meta = [
        'version' => 'v0.1.0',
        'documentation' => 'https://www.github.com/aryelgois/medools-router',
    ];

    /**
     * Limit how many resources can be returned in a collection request
     *
     * If 0, no pagination is done
     *
     * Overwitten by per_page query parameter
     *
     * @var integer
     */
    protected $per_page = 20;

    /**
     * Separator used in composite PRIMARY_KEY
     *
     * @var string
     */
    protected $primary_key_separator = '-';

    /**
     * URL that access the Router
     *
     * @var string
     */
    protected $url;

    /**
     * If should enable zlib compression when appropriate
     *
     * @var boolean
     */
    protected $zlib_compression = true;

    /*
     * Router data
     * =========================================================================
     */

    /**
     * Request's Authentication
     *
     * @var null           Authentication is disabled
     * @var false          Authorization Header is empty or missing
     * @var Authentication Request is authenticated
     */
    protected $auth;

    /**
     * Router's cache
     *
     * @var mixed[]
     */
    protected $cache = [];

    /**
     * Requested HTTP method
     *
     * @var string
     */
    protected $method;

    /**
     * List of resources available in the Router
     *
     * Each key is a resource name which maps to a Fully Qualified Model Class
     * or an array with the keys:
     * - 'model'        string          (required) Fully Qualified Model Class
     * - 'methods'      string|string[] Allowed HTTP methods. Defaults to
     *                                  IMPLEMENTED_METHODS. 'OPTIONS' is
     *                                  implicitly included
     * - 'content_type' mixed[]         Map of special Content-Types and their
     *                                  external handlers
     * - 'filters'      string|string[] List of columns that can be used as
     *                                  query parameters to filter collection
     *                                  requests
     * - 'cache'        boolean         If caching headers should be sent
     * - 'max_age'      int             Cache-Control max-age (seconds)
     * - 'public'       mixed           If can be accessed without
     *                                  authentication, and optionally which
     *                                  methods are publicly allowed
     *
     * NOTE:
     * - Resource names should be in plural
     *
     * @var mixed[]
     */
    protected $resources = [];

    /*
     * Basic methods
     * =========================================================================
     */

    /**
     * Creates a new Router object
     *
     * @param string  $url       Router URL
     * @param mixed[] $resources List of resources available
     * @param mixed[] $config    Configurations for the Router @see CONFIGURABLE
     *
     * @throws RouterException If $resources is empty
     * @throws RouterException If any $resource does not define a Model class
     * @throws RouterException If any $config key is invalid
     * @throws RouterException If any $config has invalid type
     */
    public function __construct(
        string $url,
        array $resources,
        array $config = null
    ) {
        $this->url = rtrim($url, '/');

        if (empty($resources)) {
            $this->sendError(
                static::ERROR_INTERNAL_SERVER,
                'Resource list is empty'
            );
        }

        foreach ($resources as $resource => $data) {
            if (!is_array($data)) {
                $data = ['model' => $data];
            }
            if (!array_key_exists('model', $data)) {
                $this->sendError(
                    static::ERROR_INTERNAL_SERVER,
                    "Resource '$resource' does not define a model class"
                );
            }
            $this->resources[$resource] = $data;
        }

        if ($config !== null) {
            $invalid = array_diff_key($config, static::CONFIGURABLE);
            if (!empty($invalid)) {
                $message = 'Invalid config key'
                    . (count($invalid) > 1 ? 's' : '')
                    . ": '" . implode("', '", array_keys($invalid)) . "'";
                $this->sendError(static::ERROR_INTERNAL_SERVER, $message);
            }
            foreach ($config as $property => $value) {
                $type = gettype($value);
                $expected = (array) static::CONFIGURABLE[$property];
                if (!in_array($type, $expected)) {
                    $message = "Config '$property' must be of the type "
                        . Format::naturalLanguageJoin($expected, 'or')
                        . ", $type given";
                    $this->sendError(static::ERROR_INTERNAL_SERVER, $message);
                }
                $this->$property = $value;
            }
        }
    }

    /**
     * Authenticates an Authorization Header
     *
     * @param string $auth Request Authorization Header
     * @param string $type Expected Authentication type
     *
     * @return null           If Authentication is disabled
     * @return false          If Authorization is empty (Bearer $type)
     * @return Response       If Basic Authentication was successful
     * @return Authentication If Bearer Authentication was successful
     *
     * @throws RouterException If Authorization is empty (Basic $type)
     * @throws RouterException If there is any problem with the secret
     * @throws RouterException If could not authenticate
     * @throws RouterException If could not generate the token
     * @throws RouterException If Authorization type is invalid
     */
    public function authenticate(string $auth, string $type)
    {
        $config = $this->authentication ?? null;
        if ($config === null) {
            return;
        } elseif (!is_array($config)) {
            $config = ['secret' => $config];
        }
        $config = array_merge(
            [
                'secret' => null,
                'algorithm' => 'HS256',
                'claims' => [],
                'expiration' => null,
                'verify' => false,
            ],
            $config
        );

        if ($auth === '') {
            switch ($type) {
                case 'Basic':
                    $this->sendError(
                        static::ERROR_INVALID_CREDENTIALS,
                        'Authorization Header is empty'
                    );
                    break;

                case 'Bearer':
                    return false;
                    break;
            }
            $this->sendError(
                static::ERROR_INTERNAL_SERVER,
                "Invalid Authorization type: '$type'"
            );
        }

        $stamp = time();

        if ($config['secret'] === null) {
            $this->sendError(
                static::ERROR_INTERNAL_SERVER,
                'Missing authentication secret'
            );
        }
        $secret_path = realpath($config['secret']);

        $secret_exp = null;
        if (is_dir($secret_path)) {
            $files = array_diff(scandir($secret_path), ['.', '..']);
            if (empty($files)) {
                $this->sendError(
                    static::ERROR_INTERNAL_SERVER,
                    'No secret file found'
                );
            }
            foreach ($files as $file) {
                $expiration = basename($file);
                if ($expiration > $stamp) {
                    $secret_exp = (int) $expiration;
                    break;
                }
            }
            if ($secret_exp === null) {
                $this->sendError(
                    static::ERROR_INTERNAL_SERVER,
                    'All secret files expired'
                );
            }
            $secret_file = "$secret_path/$file";
        } elseif (is_file($secret_path)) {
            $secret_file = $secret_path;
        } else {
            $this->sendError(
                static::ERROR_INTERNAL_SERVER,
                'Secret file does not exist'
            );
        }
        $secret = file_get_contents($secret_file);

        list($type_h, $credentials) = array_pad(explode(' ', $auth, 2), 2, '');
        if ($type !== $type_h) {
            $this->sendError(
                static::ERROR_INVALID_CREDENTIALS,
                "Invalid Authorization type in header: '$type_h'"
            );
        }

        switch ($type) {
            case 'Basic':
                $credentials = explode(':', base64_decode($credentials), 2);
                list($username, $password) = array_pad($credentials, 2, '');

                $authentication = Authentication::getInstance([
                    'username' => $username,
                ]);

                if ($authentication === null
                    || !$authentication->checkPassword($password)
                ) {
                    $this->sendError(
                        static::ERROR_INVALID_CREDENTIALS,
                        'Invalid credentials'
                    );
                }

                if ($authentication->isDeleted()) {
                    $this->sendError(
                        static::ERROR_UNAUTHENTICATED,
                        'Account is disabled'
                    );
                }

                if ($config['verify'] && !$authentication->verified) {
                    $this->sendError(
                        static::ERROR_UNAUTHENTICATED,
                        'Email is not verified'
                    );
                }

                $token = array_merge(
                    $config['claims'],
                    [
                        'iss' => $this->url,
                        'iat' => $stamp,
                        'user' => $authentication->id,
                    ]
                );

                $expiration = $config['expiration'];
                if ($expiration === null) {
                    if ($secret_exp !== null) {
                        $token['exp'] = $secret_exp;
                    }
                } else {
                    $expiration += $stamp;
                    $token['exp'] = ($secret_exp !== null)
                        ? min($expiration, $secret_exp)
                        : $expiration;
                }

                $response = $this->prepareResponse();
                $response->headers['Content-Type'] = 'application/jwt';

                try {
                    $response->body = JWT::encode(
                        $token,
                        $secret,
                        $config['algorithm']
                    );
                } catch (\Exception $e) {
                    $this->sendError(
                        static::ERROR_INTERNAL_SERVER,
                        'Could not generate token: ' . $e->getMessage()
                    );
                }

                return $response;
                break;

            case 'Bearer':
                try {
                    $token = JWT::decode(
                        $credentials,
                        $secret,
                        [$config['algorithm']]
                    );
                } catch (\Exception $e) {
                    $this->sendError(
                        static::ERROR_INVALID_TOKEN,
                        'Could not verify token: ' . $e->getMessage()
                    );
                }

                $authentication = Authentication::getInstance([
                    'id' => $token->user,
                ]);

                if ($authentication === null) {
                    $this->sendError(
                        static::ERROR_UNAUTHENTICATED,
                        'Account not found'
                    );
                }

                if ($authentication->isDeleted()) {
                    $this->sendError(
                        static::ERROR_UNAUTHENTICATED,
                        'Account is disabled'
                    );
                }

                return $authentication;
                break;
        }

        $this->sendError(
            static::ERROR_INTERNAL_SERVER,
            "Invalid Authorization type: '$type'"
        );
    }

    /**
     * Processes a $method request to $uri
     *
     * @param string $method  Requested HTTP method
     * @param string $uri     Requested URI
     * @param array  $headers Request Headers
     * @param string $body    Request Body
     *
     * @return Response
     * @return null     If response was sent by external handler
     *
     * @throws RouterException If $method is not implemented
     * @throws RouterException If $method is not allowed
     * @throws RouterException If $uri has invalid extension
     * @throws RouterException If Resource's Content-Type handler does not allow
     *                         requested kind
     * @throws RouterException If could not process the request
     */
    public function run(
        string $method,
        string $uri,
        array $headers,
        string $body
    ) {
        $this->auth = $this->authenticate(
            $headers['Authorization'] ?? '',
            'Bearer'
        );

        if (strcasecmp($method, 'POST') === 0) {
            $actual_method = $headers['X-Http-Method-Override'] ?? 'POST';
        }
        $this->method = strtoupper($actual_method ?? $method);

        $allow = $this->implemented_methods;
        if (!in_array($this->method, $allow)) {
            $message = "Method '$this->method' is not implemented. Please use: "
                . Format::naturalLanguageJoin($allow, 'or');
            $this->sendError(
                static::ERROR_METHOD_NOT_IMPLEMENTED,
                $message,
                $allow
            );
        }
        $safe_method = in_array($this->method, static::SAFE_METHODS);

        $resource = $this->parseRoute($uri);
        $response = null;

        if ($resource === null) {
            if ($this->method !== 'OPTIONS') {
                $response = $this->requestRoot();
            }
        } else {
            $resource_name = $resource['name'];
            $resource_extension = $resource['extension'];
            $resource_data = $this->resources[$resource_name];
            $resource_accept = $this->extensions[$resource_extension] ?? null;

            parse_str(parse_url($uri, PHP_URL_QUERY), $query);
            $data = $this->parseBody($headers['Content-Type'] ?? '', $body);
            $resource['query'] = $query;
            $resource['data'] = $data;

            $methods = (array) ($resource_data['methods'] ?? null);
            if (!empty($methods)) {
                $allow = array_intersect(
                    $allow,
                    array_merge($methods, ['OPTIONS'])
                );
                if (!in_array($this->method, $allow)) {
                    $message = "Method '$this->method' is not allowed. Please "
                        . 'use: ' . Format::naturalLanguageJoin($allow, 'or');
                    $this->sendError(
                        static::ERROR_METHOD_NOT_ALLOWED,
                        $message,
                        $allow
                    );
                }
            }

            if ($safe_method) {
                $resource_types = $this->computeResourceTypes($resource_name);
                if ($resource_accept !== null
                    && !array_key_exists($resource_accept, $resource_types)
                ) {
                    $message = "Resource '$resource_name' can not generate "
                        . "content for '$resource_extension' extension";
                    $this->sendError(static::ERROR_NOT_ACCEPTABLE, $message);
                }

                $resource['content_type'] = $accepted = $this->parseAccept(
                    $resource_name,
                    $resource_accept ?? $headers['Accept'] ?? '*/*'
                );

                $handlers = $resource_types[$accepted]['handler'];
                if (is_array($handlers)
                    && (!array_key_exists($resource['kind'], $handlers)
                        || $accepted !== 'application/json'
                        && $handlers[$resource['kind']] === null
                    )
                ) {
                    $message = "Resource '$resource_name' can not generate "
                        . $resource['content_type'] . ' ' . $resource['kind'];
                    $this->sendError(static::ERROR_NOT_ACCEPTABLE, $message);
                }

                if (($resource['content_location'] ?? null) !== null) {
                    $extension = $resource_extension ?? array_search(
                        $accepted,
                        $this->extensions
                    );
                    if (is_string($extension)) {
                        $resource['content_location'] .= ".$extension";
                    }
                    $query = http_build_query($query);
                    if ($query !== '') {
                        $resource['content_location'] .= '?' . $query;
                    }
                }
            }

            if ($this->method !== 'OPTIONS') {
                $resource_obj = new Resource;
                foreach ($resource as $key => $value) {
                    $resource_obj->$key = $value;
                }

                try {
                    $response = ($resource_obj->kind === 'collection')
                        ? $this->requestCollection($resource_obj)
                        : $this->requestResource($resource_obj);
                } catch (RouterException $e) {
                    throw $e;
                } catch (ForeignConstraintException $e) {
                    $code = static::ERROR_FOREIGN_CONSTRAINT;
                } catch (MissingColumnException $e) {
                    $code = static::ERROR_INVALID_PAYLOAD;
                } catch (ReadOnlyModelException $e) {
                    $code = static::ERROR_READONLY_RESOURCE;
                } catch (UnknownColumnException $e) {
                    $code = static::ERROR_UNKNOWN_FIELDS;
                } catch (\Exception $e) {
                    $code = static::ERROR_INTERNAL_SERVER;
                } finally {
                    if (isset($code)) {
                        $this->sendError($code, $e->getMessage());
                    }
                }

                $content_location = $resource_obj->content_location;
                if ($content_location !== null) {
                    $response->headers['Content-Location'] = $content_location;
                }

                if (headers_sent()) {
                    return;
                }
            }
        }

        if ($response === null) {
            $response = $this->prepareResponse();
            if ($this->method === 'OPTIONS') {
                $response->headers['Allow'] = $allow;
            }
        }

        if ($this->method !== 'HEAD' && !empty($response->body)
            && ($resource_data['cache'] ?? $this->always_cache)
        ) {
            $response = $this->checkCache(
                $response,
                $headers['If-None-Match'] ?? '',
                $resource_data['max_age'] ?? 0
            );
        }

        return $response;
    }

    /**
     * When requested route points to a collection
     *
     * @param Resource $resource Processed route
     *
     * @return Response
     * @return null     If response was sent by external handler
     *
     * @throws RouterException If Resource has invalid filters group
     * @throws RouterException If filter query parameter is invalid
     * @throws RouterException If per_page query parameter is invalid
     * @throws RouterException If page query parameter is invalid
     * @throws RouterException If Resource has invalid Content-Type handler
     * @throws RouterException If requesting with PUT method
     */
    protected function requestCollection(Resource $resource)
    {
        $response = $this->prepareResponse();

        $where = $resource->where;
        $safe_method = in_array($this->method, static::SAFE_METHODS);
        $resource_query = $resource->query;
        $fields = $this->parseFields($resource);
        $has_fields = ($resource->query['fields'] ?? '') !== '';

        /*
         * Filter query parameters
         */
        $filters = $this->resources[$resource->name]['filters']
            ?? $this->default_filters
            ?? [];
        if (is_string($filters)) {
            if ($filters === 'ALL') {
                $filters = $resource->model_class::COLUMNS;
            } else {
                $special = $resource->getSpecialFields();
                if (array_key_exists($filters, $special)) {
                    $filters = $special[$filters];
                } else {
                    $this->sendError(
                        static::ERROR_INTERNAL_SERVER,
                        "Resource '$resource->name' has invalid filters group"
                    );
                }
            }
        }
        $operators = static::FITLER_OPERATORS;
        $operators_single = ['gt', 'ge', 'lt', 'le', 'rx'];
        foreach ($filters as $filter) {
            $q = $resource_query[$filter] ?? null;
            $pack = [];
            if (is_array($q)) {
                foreach ($q as $key => $value) {
                    if (is_numeric($key)) {
                        $pack[''][] = $value;
                    } elseif (in_array($key, $operators_single)) {
                        if (is_array($value)) {
                            $this->sendError(
                                static::ERROR_INVALID_QUERY_PARAMETER,
                                "Filter operator '$key' can not be array"
                            );
                        }
                        $pack[$key] = $value;
                    } elseif (array_key_exists($key, $operators)) {
                        $pack[$key] = (is_array($value))
                            ? $value
                            : explode(',', $value);

                        if (in_array($key, ['bw', 'nw'])
                            && count($pack[$key]) !== 2
                        ) {
                            $this->sendError(
                                static::ERROR_INVALID_QUERY_PARAMETER,
                                "Filter operator '$key' needs two values"
                            );
                        }
                    } else {
                        $this->sendError(
                            static::ERROR_INVALID_QUERY_PARAMETER,
                            "Invalid filter operator '$key' in '$filter'"
                        );
                    }
                }
            } elseif ($q !== null) {
                $pack[''] = explode(',', $q);
            }

            foreach ($pack as $key => $value) {
                if (is_array($value) && count($value) === 1) {
                    $value = $value[0];
                }
                if (in_array($key, ['', 'ne']) && $value === 'NULL') {
                    $value = null;
                }
                if ($key !== '') {
                    $key = '[' . static::FITLER_OPERATORS[$key] . ']';
                }
                $where[$filter . $key] = $value;
            }
        }

        /*
         * Authorization filter
         */
        if ($resource->authorized !== null) {
            $where = (empty($where))
                ? $resource->authorized
                : [
                    'AND # Requested' => $where,
                    'AND # Authorized' => $resource->authorized,
                ];
        }

        /*
         * Sort query parameter
         */
        $sort = $resource_query['sort'] ?? '';
        if ($sort !== '') {
            $sort = explode(',', $sort);
            $order = [];
            foreach ($sort as $id => $value) {
                if (strpos($value, '-') === 0) {
                    $sort[$id] = $value = substr($value, 1);
                    if ($value === '') {
                        unset($sort[$id]);
                        continue;
                    }
                    $order[$value] = 'DESC';
                } elseif ($value === '') {
                    unset($sort[$id]);
                    continue;
                } else {
                    $order[] = $value;
                }
            }
            if (!empty($order)) {
                $this->checkUnknownField($resource, $sort);
                $where['ORDER'] = $order;
            }
        }

        /*
         * Per page and page query parameters
         */
        $per_page = ($safe_method || isset($resource_query['page']))
            ? $this->per_page
            : 0;
        $per_page = $resource_query['per_page'] ?? $per_page;
        if ($per_page === 'all') {
            $per_page = 0;
        }
        if (!is_numeric($per_page) || $per_page < 0) {
            $this->sendError(
                static::ERROR_INVALID_QUERY_PARAMETER,
                "Invalid 'per_page' parameter"
            );
        } elseif ($per_page > 0) {
            $page = $resource_query['page'] ?? 1;
            if (!is_numeric($page) || $page < 1) {
                $this->sendError(
                    static::ERROR_INVALID_QUERY_PARAMETER,
                    "Invalid 'page' parameter"
                );
            }

            if ($safe_method) {
                $count = $this->countResource($resource->name, $where);
                $pages = ceil($count / $per_page);
                $routes = [];

                $tmp = $resource_query;
                $tmp['page'] = 1;
                $routes['first'] = http_build_query($tmp);
                if ($page > 1) {
                    $tmp['page'] = $page - 1;
                    $routes['previous'] = http_build_query($tmp);
                }
                if ($page < $pages) {
                    $tmp['page'] = $page + 1;
                    $routes['next'] = http_build_query($tmp);
                }
                $tmp['page'] = $pages;
                $routes['last'] = http_build_query($tmp);

                foreach ($routes as &$route) {
                    $route = $resource->route . '?' . $route;
                }
                unset($route);

                $response->headers['Link'] = $this->headerLink($routes);
                $response->headers['X-Total-Count'] = $count;
            }

            $where['LIMIT'] = [($page - 1) * $per_page, $per_page];
        }

        $resource->where = $where;

        /*
         * Process request
         */
        $body = null;
        switch ($this->method) {
            case 'GET':
            case 'HEAD':
                $resource_types = $this->computeResourceTypes($resource->name);
                $handler = $resource_types[$resource->content_type]['handler'];
                if (is_array($handler)) {
                    $handler = $handler[$resource->kind];
                }
                if ($handler !== null) {
                    if (is_callable($handler)) {
                        if ($this->method === 'HEAD') {
                            ob_start();
                            $handler($resource);
                            ob_end_clean();
                        } else {
                            $handler($resource);
                            ob_flush();
                        }
                        return;
                    } else {
                        $message = "Resource '$resource->name' has invalid "
                            . "$resource->content_type handler (collection)";
                        $this->sendError(
                            static::ERROR_INTERNAL_SERVER,
                            $message
                        );
                    }
                }

                $body = $resource->model_class::dump($where, $fields);
                break;

            case 'DELETE':
                $list = [];
                foreach ($resource->getIterator() as $id => $model) {
                    $route = $resource->route . '/'
                        . (strrpos($resource->route, '/') == 0
                            ? $this->getPrimaryKey($model)
                            : $id + 1);

                    $this->deleteModel($model, $resource, $route);

                    if ($model::SOFT_DELETE !== null) {
                        $tmp = $model->getData();
                        if (!empty($fields)) {
                            $tmp = Utils::arrayWhitelist($tmp, $fields);
                        } elseif ($has_fields) {
                            continue;
                        }
                        $list[] = $tmp;
                    }
                }
                if (!empty($list)) {
                    $body = $list;
                }
                break;

            case 'PATCH':
                $body = [];
                foreach ($resource->getIterator() as $id => $model) {
                    $route = $resource->route . '/'
                        . (strrpos($resource->route, '/') == 0
                            ? $this->getPrimaryKey($model)
                            : $id + 1);

                    $this->updateModel($model, $resource, $route);

                    $tmp = $model->getData();
                    if (!empty($fields)) {
                        $tmp = Utils::arrayWhitelist($tmp, $fields);
                    } elseif ($has_fields) {
                        continue;
                    }
                    $body[] = $tmp;
                }
                break;

            case 'POST':
                $this->checkMissingFields($resource);

                $model = $this->createModel($resource);
                $location = $this->getContentLocation($model, $resource);

                $response->status = HttpResponse::HTTP_CREATED;
                $response->headers['Location'] = $location;
                break;

            case 'PUT':
                $this->sendError(
                    static::ERROR_METHOD_NOT_ALLOWED,
                    'Collections do not allow PUT Method'
                );
                break;
        }

        if ($body !== null) {
            $response->headers['Content-Type'] = 'application/json';
            $response->body = $body;
        }

        return $response;
    }

    /**
     * When requested route points to a resource
     *
     * @param Resource $resource Processed route
     *
     * @return Response
     * @return null     If response was sent by external handler
     *
     * @throws RouterException If Resource has invalid Content-Type handler
     * @throws RouterException If requesting with POST method
     */
    protected function requestResource(Resource $resource)
    {
        $response = $this->prepareResponse();

        $resource_class = $resource->model_class;
        $fields = $this->parseFields($resource);
        $has_fields = ($resource->query['fields'] ?? '') !== '';

        if ($resource->exists) {
            $model = $resource_class::getInstance($resource->where);
        }

        switch ($this->method) {
            case 'GET':
            case 'HEAD':
                $resource_types = $this->computeResourceTypes($resource->name);
                $handler = $resource_types[$resource->content_type]['handler'];
                if (is_array($handler)) {
                    $handler = $handler[$resource->kind];
                }
                if ($handler !== null) {
                    if (is_callable($handler)) {
                        if ($this->method === 'HEAD') {
                            ob_start();
                            $handler($resource);
                            ob_end_clean();
                        } else {
                            $handler($resource);
                            ob_flush();
                        }
                        return;
                    } else {
                        $message = "Resource '$resource->name' has invalid "
                            . "$resource->content_type handler";
                        $this->sendError(
                            static::ERROR_INTERNAL_SERVER,
                            $message
                        );
                    }
                }
                break;

            case 'DELETE':
                $this->deleteModel($model, $resource);
                break;

            case 'PATCH':
                $this->updateModel($model, $resource);

                $location = $this->getContentLocation($model, $resource);
                $resource->content_location = $location;
                break;

            case 'POST':
                $this->sendError(
                    static::ERROR_METHOD_NOT_ALLOWED,
                    'Resources do not allow POST Method'
                );
                break;

            case 'PUT':
                $resource->data = array_merge(
                    $resource->where,
                    $resource->data
                );

                $this->checkMissingFields($resource);

                if ($resource->exists) {
                    /*
                     * Trying to clear optional columns
                     *
                     * It is better than deleting the model and re-creating it,
                     * because could trigger ForeignConstraintException
                     */
                    $optional = array_filter(array_merge(
                        $model::OPTIONAL_COLUMNS,
                        [$model::AUTO_INCREMENT],
                        $model::getAutoStampColumns()
                    ));
                    $optional = array_diff($optional, $model::PRIMARY_KEY);
                    foreach ($optional as $column) {
                        $model->$column = null;
                    }
                    if ($model::SOFT_DELETE !== null) {
                        $model->undelete();
                    }

                    $this->updateModel($model, $resource);

                    $location = $this->getContentLocation($model, $resource);
                } else {
                    $model = $this->createModel($resource);
                    $location = $this->getContentLocation($model, $resource);

                    $response->status = HttpResponse::HTTP_CREATED;
                    $response->headers['Location'] = $location;
                }
                $resource->content_location = $location;
                break;
        }

        if ($this->method !== 'DELETE' || $model::SOFT_DELETE !== null) {
            $expand = $resource->query['expand'] ?? null;
            if ($expand === 'false'
                || !$this->always_expand && $expand === null
            ) {
                $body = $model->getData();

                $foreigns = $this->getForeignRoutes($model, $fields);
                if (!empty($foreigns)) {
                    $response->headers['Link'] = $this->headerLink($foreigns);
                }
            } else {
                $body = $model->toArray();
            }
            if (!empty($fields)) {
                $body = Utils::arrayWhitelist($body, $fields);
            } elseif ($has_fields) {
                $body = null;
            }

            $response->headers['Content-Type'] = 'application/json';
            $response->body = $body;
        }

        return $response;
    }

    /**
     * When requested route is '/'
     *
     * @return Response With $meta and a row count for each resource. If $meta
     *                  is empty, only the resource count is included
     */
    protected function requestRoot()
    {
        $authorization = $this->getAuthorizedResources();

        $resources = Utils::arrayWhitelist(
            $this->resources,
            array_keys($authorization)
        );

        if (empty($resources)) {
            $this->sendError(
                static::ERROR_UNAUTHORIZED,
                'You can not access the index'
            );
        }

        $count = [];
        foreach (array_keys($resources) as $resource) {
            $count[$resource] = $this->countResource(
                $resource,
                $authorization[$resource]
            );
        }

        $response = $this->prepareResponse();
        $response->headers['Content-Type'] = 'application/json';
        $response->body = (empty($this->meta))
            ? $count
            : array_merge($this->meta, ['resources' => $count]);

        return $response;
    }

    /*
     * Parsers
     * =========================================================================
     */

    /**
     * Parses Accept header
     *
     * NOTE:
     * - If $resource does not comply to $accept, but it does not forbid any of
     *   $resource's content types (i.e. ';q=0'), this function returns the
     *   first $resource's content type with highest priority. It is better to
     *   return something the client doesn't complain about than a useless error
     *
     * @param string $resource Resource name
     * @param string $accept   Request Accept
     *
     * @return string
     *
     * @throws RouterException If Resource has invalid Content-Type
     * @throws RouterException If no Content-Type is acceptable
     */
    protected function parseAccept(string $resource, string $accept)
    {
        $available_types = [];
        $resource_types = $this->computeResourceTypes($resource);
        foreach ($resource_types as $resource_type => $data) {
            $available_types[$resource_type] = $data['priority'];
        }

        $list = [];
        $accept_types = explode(',', $accept);
        foreach ($accept_types as $fragment) {
            $fragment = explode(';', $fragment);
            $accept_type = trim($fragment[0]);
            $priority = ((float) filter_var(
                $fragment[1] ?? 1,
                FILTER_SANITIZE_NUMBER_FLOAT,
                FILTER_FLAG_ALLOW_FRACTION
            ));
            $priority = Utils::numberLimit($priority, 0, 1);
            if (strpos($accept_type, '*') === false) {
                if (array_key_exists($accept_type, $available_types)) {
                    $list[$accept_type] = max(
                        $list[$accept_type] ?? 0,
                        $priority * $available_types[$accept_type]
                    );
                }
            } else {
                $priority -= 0.0001;
                foreach ($available_types as $resource_type => $value) {
                    if ($value > 0 && fnmatch($accept_type, $resource_type)) {
                        $list[$resource_type] = max(
                            $list[$resource_type] ?? 0,
                            $priority * $value
                        );
                    }
                }
            }
        }

        if (empty($list)) {
            $result = static::firstHigher($available_types);
            if ($result === null) {
                $this->sendError(
                    static::ERROR_INTERNAL_SERVER,
                    "Resource '$resource' has invalid Content-Type"
                );
            }
        } else {
            $result = static::firstHigher($list);
            if ($result === null) {
                $message = "Resource '$resource' can not generate content"
                    . ' complying to Accept header';
                $this->sendError(static::ERROR_NOT_ACCEPTABLE, $message);
            }
        }
        return $result;
    }

    /**
     * Parses request body
     *
     * @param string $type Request Content-Type
     * @param string $body Request Body
     *
     * @return array
     *
     * @throws RouterException If Request Content-Type is not supported
     * @throws RouterException If Request Body could not be parsed
     */
    protected function parseBody(string $type, string $body)
    {
        $content_type = explode(';', $type, 2)[0];

        if ($content_type === '') {
            if ($body === '') {
                return [];
            }
        } elseif ($content_type === 'application/json') {
            $data = json_decode($body, true);
            if (!empty($data)) {
                return $data;
            }
        } else {
            $this->sendError(
                static::ERROR_UNSUPPORTED_MEDIA_TYPE,
                "Content-Type '$content_type' is not supported"
            );
        }

        $this->sendError(
            static::ERROR_INVALID_PAYLOAD,
            "Content Body could not be parsed"
        );
    }

    /**
     * Parses fields query and validate against a resource
     *
     * @param Resource $resource Resource
     *
     * @return string[]
     *
     * @throws RouterException If fields query parameter has invalid fields
     */
    protected function parseFields(Resource $resource)
    {
        $fields = $resource->getFields();
        if (!is_array($fields)) {
            $this->sendError(static::ERROR_UNKNOWN_FIELDS, $fields);
        }
        return $fields;
    }

    /**
     * Parses a URI route
     *
     * @param string $uri Route to be parsed
     *
     * @return mixed[] On success
     * @return null    On failure
     *
     * @throws RouterException If requesting invalid resource
     * @throws RouterException If resource is incorrectly nested
     * @throws RouterException If route has invalid resource id
     * @throws RouterException If route has invalid collection offset
     * @throws RouterException If resource was not found. It may exist but the
     *                         user is not authorized to see it
     */
    protected function parseRoute(string $uri)
    {
        $result = [];

        $route = trim(urldecode(parse_url($uri, PHP_URL_PATH)), '/');
        if ($route === '') {
            return;
        }
        $extension = null;
        if (!empty($this->extensions)) {
            foreach (array_keys($this->extensions) as $ext) {
                $ext = ".$ext";
                $len = strlen($ext) * -1;
                if (substr($route, $len) === $ext) {
                    $route = substr($route, 0, $len);
                    $extension = substr($ext, 1);
                    break;
                }
            }
        }
        $result['extension'] = $extension;
        $result['route'] = "/$route";
        $route = explode('/', $route);

        $model = $previous = null;
        $length = count($route);
        for ($i = 0; $i < $length; $i += 2) {
            $resource = $route[$i];
            $resource_data = $this->resources[$resource] ?? null;
            if ($resource_data === null) {
                $this->sendError(
                    static::ERROR_INVALID_RESOURCE,
                    "Invalid resource '$resource'"
                );
            }
            $resource_class = $resource_data['model'];

            $id = $route[$i + 1] ?? null;
            $is_last = ($route[$i + 2] ?? null) === null;

            if (!$this->isPublic(
                    $resource,
                    ($is_last ? $this->method : static::SAFE_METHODS)
                )
            ) {
                $code = static::ERROR_UNAUTHORIZED;
                if ($this->auth instanceof Authentication) {
                    $authorization = Authorization::getInstance([
                        'user' => $this->auth->id,
                        'resource' => $resource,
                    ]);

                    if ($authorization !== null) {
                        $methods = $authorization->methods;
                        $allowed = $methods === null
                            || in_array(
                                ($is_last ? $this->method : 'GET'),
                                json_decode($methods, true)
                            );

                        if ($allowed) {
                            $code = null;
                            $filter = $authorization->filter;
                            $authorized = json_decode($filter, true);
                        }
                    }
                }
                if (isset($code)) {
                    $this->sendError($code, "You can not access '$resource'");
                }
            }

            if ($id === null) {
                $where = ($model !== null)
                    ? static::reverseForeignKey($resource_class, $model)
                    : [];
                if ($where === null) {
                    $message = "Resource '$resource' does not have foreign key "
                        . "for '$previous'";
                    $this->sendError(
                        static::ERROR_INVALID_RESOURCE_FOREIGN,
                        $message
                    );
                }

                return array_merge(
                    $result,
                    [
                        'name' => $resource,
                        'kind' => 'collection',
                        'model_class' => $resource_class,
                        'where' => $where,
                        'authorized' => $authorized ?? null,
                    ]
                );
            } else {
                if ($model === null) {
                    $where = @array_combine(
                        $resource_class::PRIMARY_KEY,
                        explode($this->primary_key_separator, $id)
                    );
                    if ($where === false) {
                        $this->sendError(
                            static::ERROR_INVALID_RESOURCE_ID,
                            "Invalid resource id for '$resource': '$id'"
                        );
                    }

                    if (isset($authorized)) {
                        $where = [
                            'AND # Requested' => $where,
                            'AND # Authorized' => $authorized,
                        ];
                    }
                } else {
                    $where = static::reverseForeignKey($resource_class, $model);
                    if ($where === null) {
                        $message = "Resource '$resource' does not have foreign "
                            . "key for '$previous'";
                        $this->sendError(
                            static::ERROR_INVALID_RESOURCE_FOREIGN,
                            $message
                        );
                    }

                    if (isset($authorized)) {
                        $where = [
                            'AND # Requested' => $where,
                            'AND # Authorized' => $authorized,
                        ];
                    }

                    $collection = $resource_class::dump(
                        $where,
                        $resource_class::PRIMARY_KEY
                    );
                    $where = $collection[$id - 1] ?? null;
                    if ($where === null) {
                        if ($is_last && $this->method === 'PUT') {
                            $exists = false;
                        } else {
                            $message = 'Invalid collection offset for '
                                . "'$resource': '$id'";
                            $this->sendError(
                                static::ERROR_INVALID_RESOURCE_OFFSET,
                                $message
                            );
                        }
                    } else {
                        $content_location = "$this->url/$resource/" . implode(
                            $this->primary_key_separator,
                            $where
                        );
                    }
                }

                $model = ($where !== null)
                    ? $resource_class::getInstance($where)
                    : null;
                if ($model === null) {
                    if ($is_last && $this->method === 'PUT') {
                        $exists = false;
                    } else {
                        $this->sendError(
                            static::ERROR_RESOURCE_NOT_FOUND,
                            "Resource '$resource/$id' not found"
                        );
                    }
                }

                if ($is_last) {
                    return array_merge(
                        $result,
                        [
                            'name' => $resource,
                            'kind' => 'resource',
                            'model_class' => $resource_class,
                            'where' => $where,
                            'exists' => $exists ?? true,
                            'content_location' => $content_location ?? null,
                        ]
                    );
                }
            }
            $previous = $resource;
        }
    }

    /*
     * Modify Database
     * =========================================================================
     */

    /**
     * Creates a Model in the Database
     *
     * @param Resource $resource Processed route
     *
     * @return Model
     *
     * @throws RouterException If could not save the new Model
     */
    protected function createModel(Resource $resource)
    {
        $model = new $resource->model_class;
        $model->fill($resource->data);

        $result = $model->save();
        if ($result === true) {
            return $model;
        }

        $code = (empty($resource->data) || $result === null)
            ? static::ERROR_INVALID_PAYLOAD
            : static::ERROR_INTERNAL_SERVER;

        $message = "Resource '$resource->name' could not be created: ";

        if (is_string($result)) {
            $message .= "Database failure ($result)";
        } elseif ($result === false) {
            $message .= 'post-save failure';
        } else {
            $message .= 'pre-save failure';
        }

        $this->sendError($code, $message);
    }

    /**
     * Deletes a Model
     *
     * @param Model    $model    Model to be deleted
     * @param Resource $resource Resource that loaded $model
     * @param string   $route    Alternative route to $model
     *
     * @throws RouterException If could not delete the Model
     */
    protected function deleteModel(
        Model $model,
        Resource $resource,
        string $route = null
    ) {
        if ($model->delete()) {
            return;
        }

        $message = "Resource '" . ($route ?? $resource->route)
            . "' could not be deleted";

        $this->sendError(static::ERROR_INTERNAL_SERVER, $message);
    }

    /**
     * Updates a Model
     *
     * @param Model    $model    Model to be updated
     * @param Resource $resource Resource that loaded $model
     * @param string   $route    Alternative route to $model
     *
     * @throws RouterException If could not update the Model
     */
    protected function updateModel(
        Model $model,
        Resource $resource,
        string $route = null
    ) {
        $model->fill($resource->data);

        $result = $model->update(array_keys($resource->data));
        if ($result === true) {
            return;
        }

        $message = "Resource '" . ($route ?? $resource->route)
            . "' could not be updated";

        if (is_string($result)) {
            $message .= ": Database failure ($result)";
        }

        $this->sendError(static::ERROR_INTERNAL_SERVER, $message);
    }

    /*
     * Internal methods
     * =========================================================================
     */

    /**
     * Checks if Response is modified from Client's cache
     *
     * It adds caching headers
     *
     * @param Response $response Response to be checked
     * @param string   $e_tags   Request If-None-Match Header
     * @param integer  $max_age  Resource max_age
     *
     * @return Response With caching headers or with Not Modified status
     *
     * @throws RouterException If 'cache_method' config is invalid
     */
    protected function checkCache(
        Response $response,
        string $e_tags,
        int $max_age
    ) {
        $result = $response;

        $cache_method = $this->cache_method;
        if (is_callable($cache_method)) {
            $hash = '"' . $cache_method(serialize($response->body)) . '"';

            if (strpos($e_tags, $hash) !== false) {
                $reuse_headers = Utils::arrayWhitelist(
                    $response->headers,
                    [
                        'Content-Location',
                    ]
                );

                $result = $this->prepareResponse();
                $result->status = HttpResponse::HTTP_NOT_MODIFIED;
                $result->headers = $reuse_headers;
            }

            $result->headers['ETag'] = $hash;
            $result->headers['Cache-Control'] = 'private, max-age=' . $max_age
                . ', must-revalidate';
        } else {
            $this->sendError(
                static::ERROR_INTERNAL_SERVER,
                "Router config 'cache_method' is invalid"
            );
        }

        return $result;
    }

    /**
     * Checks if resource data fulfills the required columns
     *
     * @param Resource $resource Resource
     *
     * @throws RouterException If required fields are missing
     */
    protected function checkMissingFields(Resource $resource)
    {
        $missing = array_diff(
            $resource->model_class::getRequiredColumns(),
            array_keys($resource->data)
        );

        if (!empty($missing)) {
            $message = "Resource '$resource->name' requires the following "
                . "missing fields: '" . implode("', '", $missing) . "'";
            $this->sendError(static::ERROR_INVALID_PAYLOAD, $message);
        }
    }

    /**
     * Checks if a resource has all fields passed
     *
     * @param Resource $resource Resource
     * @param string[] $fields   List of fields to test
     *
     * @throws RouterException If there are unknown fields
     */
    protected function checkUnknownField(Resource $resource, array $fields)
    {
        $message = $resource->hasFields($fields);
        if ($message !== true) {
            $this->sendError(static::ERROR_UNKNOWN_FIELDS, $message);
        }
    }

    /**
     * Computes Resource Content Types
     *
     * NOTE:
     * - It caches results
     *
     * @param string $resource Resource name
     *
     * @return array[]
     *
     * @throws RouterException If resource's Content-Type is invalid
     */
    protected function computeResourceTypes(string $resource)
    {
        $cached = $this->cache['resource_types'][$resource] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $resource_types = array_replace_recursive(
            $this->default_content_type,
            $this->resources[$resource]['content_type'] ?? []
        );
        foreach ($resource_types as $resource_type => &$data) {
            if (!is_array($data)) {
                $data = ['handler' => $data];
            }
            if (!array_key_exists('handler', $data)) {
                $message = "Content-Type '$resource_type' for resource "
                    . "'$resource' is invalid";
                $this->sendError(static::ERROR_INTERNAL_SERVER, $message);
            }
            $data['priority'] = $data['priority'] ?? 1;
        }
        unset($data);

        $this->cache['resource_types'][$resource] = $resource_types;
        return $resource_types;
    }

    /**
     * Counts rows in Resource's table
     *
     * @param string  $resource Resource name
     * @param mixed[] $where    \Medoo\Medoo $where clause
     *
     * @return integer
     */
    protected function countResource(string $resource, array $where = null)
    {
        $model = $this->resources[$resource]['model'];
        $database = $model::getDatabase();
        return $database->count($model::TABLE, $where ?? []);
    }

    /**
     * Returns key for first highest value
     *
     * @param float[] $list List of numbers between min and max
     * @param float   $min  Min value to test
     * @param float   $max  Max value to test
     *
     * @return string
     * @return null   If no value was higher than $min
     */
    protected static function firstHigher(
        array $list,
        float $min = null,
        float $max = null
    ) {
        $min = $min ?? 0;
        $max = $max ?? 1;

        $bounded_list = array_merge([$min, $max], $list);

        $min = min($bounded_list);
        $max = max($bounded_list);

        $result = null;
        $higher = $min;
        foreach ($list as $id => $value) {
            $value = Utils::numberLimit($value, $min, $max);
            if ($value == $max) {
                return $id;
            } elseif ($value > $higher) {
                $result = $id;
                $higher = $value;
            }
        }

        return $result;
    }

    /**
     * Returns a list of allowed methods for a resource
     *
     * NOTE:
     * - It caches results
     *
     * @param string $resource Resource name
     *
     * @return string[]
     *
     * @throws \DomainException If $resource is invalid
     */
    protected function getAllowedMethods(string $resource)
    {
        $cached = $this->cache['allowed_methods'][$resource] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $resource_data = $this->resources[$resource] ?? null;
        if ($resource_data === null) {
            throw new \DomainException("Invalid resource '$resource'");
        }

        $allow = $this->implemented_methods;

        $methods = (array) ($resource_data['methods'] ?? null);
        if (!empty($methods)) {
            $allow = array_intersect(
                $allow,
                array_merge($methods, ['OPTIONS'])
            );
        }

        $this->cache['allowed_methods'][$resource] = $allow;
        return $allow;
    }

    /**
     * Returns authorized resources for the authenticated user and their filters
     *
     * @param string|string[] $methods Which methods to test
     *                                 Default is requested method
     *
     * @return mixed[] With 'resource' => filter
     */
    protected function getAuthorizedResources($methods = null)
    {
        $resources = [];

        if ($this->auth instanceof Authentication) {
            $allow = (array) ($methods ?? $this->method);

            $authorizations = Authorization::dump(
                [
                    'user' => $this->auth->id,
                ],
                [
                    'resource',
                    'methods',
                    'filter',
                ]
            );

            foreach ($authorizations as $authorization) {
                $resource = $authorization['resource'];
                if (array_key_exists($resource, $this->resources)) {
                    if ($this->isPublic($resource, $methods)) {
                        $resources[$resource] = null;
                    } elseif (!empty(array_intersect(
                        $this->getAllowedMethods($resource),
                        array_merge(
                            $authorization['methods'] ?? [],
                            $allow
                        )
                    ))) {
                        $resources[$resource] = $authorization['filter'];
                    }
                }
            }
        } else {
            $resources = array_keys($this->resources);

            if ($this->auth === false) {
                foreach ($resources as $id => $resource) {
                    if (!$this->isPublic($resource, $methods)) {
                        unset($resources[$id]);
                    }
                }
            }

            $resources = array_fill_keys(array_values($resources), null);
        }

        return $resources;
    }

    /**
     * Returns Content-Location for a Model in a Resource
     *
     * @param Model    $model    Model to get Location
     * @param Resource $resource Resource that loaded $model
     *
     * @return string
     */
    protected function getContentLocation(Model $model, Resource $resource)
    {
        $query = http_build_query($resource->query);

        $content_location = "$this->url/$resource->name/"
            . $this->getPrimaryKey($model)
            . ($query !== '' ? '?' . $query : '');

        return $content_location;
    }

    /**
     * Returns list of routes to Model's Foreigns
     *
     * @param Model    $model  Model whose Foreigns' routes will be returned
     * @param string[] $filter Only returns routes for foreigns listed here
     *                         Invalid columns are silently ignored
     *
     * @return string[]
     */
    protected function getForeignRoutes(Model $model, array $filter = null)
    {
        $routes = [];

        $foreigns = $model::FOREIGN_KEYS;
        if (!empty($filter)) {
            $foreigns = Utils::arrayWhitelist($foreigns, $filter);
        }

        foreach ($foreigns as $column => $fk) {
            foreach ($this->resources as $resource_name => $resource_data) {
                if ($resource_data['model'] === $fk[0]) {
                    $foreign = $model->$column;
                    if ($foreign !== null) {
                        $routes[$column] = "/$resource_name/"
                            . $this->getPrimaryKey($foreign);
                    }
                    break;
                }
            }
        }

        return $routes;
    }

    /**
     * Returns normalized Model's Primary Key
     *
     * @param Model $model Model whose Primary Key will be returned
     *
     * @return string
     */
    protected function getPrimaryKey(Model $model)
    {
        return implode($this->primary_key_separator, $model->getPrimaryKey());
    }

    /**
     * Generates content for Link header
     *
     * @param string[] $routes List of routes
     *
     * @return string
     */
    protected function headerLink(array $routes)
    {
        $links = [];
        foreach ($routes as $rel => $route) {
            $links[] = '<' . $this->url . $route . '>; rel="' . $rel . '"';
        }
        return implode(', ', $links);
    }

    /**
     * Tells if a resource's method has public access
     *
     * @param string          $resource Resource name
     * @param string|string[] $methods  Which methods to test
     *                                  Default is requested method
     *
     * @return boolean For success or failure
     */
    protected function isPublic(string $resource, $methods = null)
    {
        if (($this->authentication ?? null) === null) {
            return true;
        }

        $resource_data = $this->resources[$resource] ?? null;
        if ($resource_data === null) {
            return false;
        }

        $public = $resource_data['public'] ?? $this->default_publicity;
        if (is_string($public)) {
            $public = [$public];
        }

        $methods = (array) ($methods ?? $this->method);

        return (is_array($public))
            ? !empty(array_intersect($public, $methods))
            : (bool) $public;
    }

    /**
     * Creates a new Response object with some properties filled
     *
     * @return Response
     */
    protected function prepareResponse()
    {
        $response = new Response;
        $response->method = $this->method;
        $response->zlib_compression = $this->zlib_compression;
        return $response;
    }

    /**
     * Finds the Foreign Key for a Model instance from a Model class
     *
     * NOTE:
     * - If $model_class has multiple foreign keys for $target, only the first
     *   one is used. You should redesign your database, duplicate the target
     *   class with a new name or create a new class that extends target, if you
     *   want to match later $model_class foreign keys
     *
     * @param string $model_class Model with Foreign Key pointing to $target
     * @param Model  $target      Model pointed by $model_class
     *
     * @return mixed[] \Medoo\Medoo $where clause for $target, using a Foreign
     *                 Key in $model_class
     * @return null    On failure
     */
    protected static function reverseForeignKey(
        string $model_class,
        Model $target
    ) {
        $target_class = get_class($target);

        $column = null;
        $found = false;
        foreach ($model_class::FOREIGN_KEYS as $column => $fk) {
            if ($target_class === $fk[0]) {
                $found = true;
                break;
            }
        }
        if ($column === null || $found === false) {
            return;
        }

        return [$column => $target->__get($fk[1])];
    }

    /**
     * Sends HTTP header and body (if requested) for an Error
     *
     * @param int    $code    Error code
     * @param string $message Error message
     * @param mixed  $data    Additional data used by some error codes
     *
     * @throws RouterException With error Response
     */
    protected function sendError(
        int $code,
        string $message,
        $data = null
    ) {
        $response = $this->prepareResponse();

        switch ($code) {
            case static::ERROR_INTERNAL_SERVER:
                $status = HttpResponse::HTTP_INTERNAL_SERVER_ERROR;
                break;

            case static::ERROR_INVALID_CREDENTIALS:
            case static::ERROR_UNAUTHENTICATED:
                $status = HttpResponse::HTTP_UNAUTHORIZED;
                $realm = $this->authentication['realm'] ?? null;
                $auth = 'Basic'
                    . ($realm !== null ? ' realm="' . $realm . '"' : '')
                    . ' charset="UTF-8"';
                $response->headers['WWW-Authenticate'] = $auth;
                break;

            case static::ERROR_INVALID_TOKEN:
                $status = HttpResponse::HTTP_UNAUTHORIZED;
                $realm = $this->authentication['realm'] ?? null;
                $auth = 'Bearer'
                    . ($realm !== null ? ' realm="' . $realm . '"' : '');
                $response->headers['WWW-Authenticate'] = $auth;
                break;

            case static::ERROR_METHOD_NOT_IMPLEMENTED:
                $status = HttpResponse::HTTP_NOT_IMPLEMENTED;
                $response->headers['Allow'] = $data;
                break;

            case static::ERROR_UNAUTHORIZED:
                $status = HttpResponse::HTTP_FORBIDDEN;
                break;

            case static::ERROR_INVALID_RESOURCE:
            case static::ERROR_INVALID_RESOURCE_ID:
            case static::ERROR_INVALID_RESOURCE_OFFSET:
            case static::ERROR_INVALID_RESOURCE_FOREIGN:
            case static::ERROR_INVALID_PAYLOAD:
            case static::ERROR_INVALID_QUERY_PARAMETER:
            case static::ERROR_UNKNOWN_FIELDS:
            case static::ERROR_READONLY_RESOURCE:
            case static::ERROR_FOREIGN_CONSTRAINT:
                $status = HttpResponse::HTTP_BAD_REQUEST;
                break;

            case static::ERROR_RESOURCE_NOT_FOUND:
                $status = HttpResponse::HTTP_NOT_FOUND;
                break;

            case static::ERROR_UNSUPPORTED_MEDIA_TYPE:
                $status = HttpResponse::HTTP_UNSUPPORTED_MEDIA_TYPE;
                break;

            case static::ERROR_METHOD_NOT_ALLOWED:
                $status = HttpResponse::HTTP_METHOD_NOT_ALLOWED;
                if ($data !== null) {
                    $response->headers['Allow'] = $data;
                }
                break;

            case static::ERROR_NOT_ACCEPTABLE:
                $status = HttpResponse::HTTP_NOT_ACCEPTABLE;
                break;

            case static::ERROR_UNKNOWN_ERROR:
            default:
                $actual_code = static::ERROR_UNKNOWN_ERROR;
                $status = HttpResponse::HTTP_INTERNAL_SERVER_ERROR;
                $message = 'Unknown error'
                    . (strlen($message) > 0 ? ': ' . $message : '');
                break;
        }
        $response->status = $status;

        $response->headers['Content-Type'] = 'application/json';
        $response->body = [
            'code' => $code,
            'message' => $message,
        ];

        throw new RouterException($response, $actual_code ?? $code);
    }
}
