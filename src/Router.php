<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\MedoolsRouter;

use aryelgois\Utils\Utils;
use aryelgois\Utils\HttpResponse;
use aryelgois\Medools\Model;
use aryelgois\MedoolsRouter\Exceptions\RouteException;

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
     * Router configurations
     * =========================================================================
     */

    /**
     * List of configurable properties and their types
     *
     * @const string[]
     */
    const CONFIGURABLE = [
        'always_expand'         => 'boolean',
        'default_content_type'  => 'array',
        'extensions'            => 'array',
        'implemented_methods'   => 'array',
        'meta'                  => 'array',
        'per_page'              => 'integer',
        'primary_key_separator' => 'string',
        'zlib_compression'      => 'boolean',
    ];

    /**
     * If foreign models are always expanded in a resource request
     *
     * @var boolean
     */
    protected $always_expand = false;

    /**
     * List of HTTP methods implemented
     *
     * @var float[]
     */
    protected $default_content_type = [
        'application/json' => [
            'handler' => null,
            'priority' => 1,
        ],
    ];

    /**
     * List of known extensions and their related content type
     *
     * Useful to ensure browser explorability via address bar
     *
     * It is intended to be configured in __construct() for custom content types
     * your resources use.
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
    ];

    /**
     * Information about your Router and API
     *
     * Returned when requesting route '/'
     *
     * @var mixed[]
     */
    protected $meta = [
        'version' => 'v0.1.0',
        'documentation' => 'https://www.github.com/aryelgois/medools-router'
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
     * NOTE:
     * - Resource names should be in plural
     *
     * @var array[]
     */
    protected $resources = [];

    /*
     * Errors
     * =========================================================================
     */

    const ERROR_INTERNAL_SERVER = 1;
    const ERROR_METHOD_NOT_IMPLEMENTED = 2;
    const ERROR_INVALID_RESOURCE = 3;
    const ERROR_INVALID_RESOURCE_ID = 4;
    const ERROR_INVALID_RESOURCE_OFFSET = 5;
    const ERROR_INVALID_RESOURCE_FOREIGN = 6;
    const ERROR_RESOURCE_NOT_FOUND = 7;
    const ERROR_UNSUPPORTED_MEDIA_TYPE = 8;
    const ERROR_INVALID_PAYLOAD = 9;
    const ERROR_METHOD_NOT_ALLOWED = 10;
    const ERROR_NOT_ACCEPTABLE = 11;
    const ERROR_INVALID_QUERY_PARAMETER = 12;
    const ERROR_UNKNOWN_FIELDS = 13;

    /*
     * Basic methods
     * =========================================================================
     */

    /**
     * Creates a new Router object
     *
     * @param string $url       Router URL
     * @param array  $resources List of resources available
     * @param array  $config    Configurations for the Router @see CONFIGURABLE
     */
    public function __construct(
        string $url,
        array $resources,
        array $config = null
    ) {
        $this->url = rtrim($url, '/');

        foreach ($resources as $resource => $data) {
            if (gettype($data) === 'string') {
                $data = ['model' => $data];
            }
            $data = (array) $data;
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
                    . ": '" . implode("', '", $invalid) . "'";
                $this->sendError(static::ERROR_INTERNAL_SERVER, $message);
            }
            foreach ($config as $property => $value) {
                $type = gettype($value);
                $expected = static::CONFIGURABLE[$property];
                if ($type !== $expected) {
                    $message = "Key '$property' in Argument 2 passed to "
                        . __METHOD__ . "() must be of the type $expected,"
                        . " $type given";
                    $this->sendError(static::ERROR_INTERNAL_SERVER, $message);
                }
                $this->$property = $value;
            }
        }
    }

    /**
     * Processes a $method request to $uri
     *
     * @param string $method Requested HTTP method
     * @param string $uri    Requested URI
     * @param string $accept Request Accept
     * @param string $type   Request Content-Type
     * @param string $body   Request body
     *
     * @return Response
     * @return null     If response was sent by external handler
     */
    public function run(
        string $method,
        string $uri,
        string $accept,
        string $type,
        string $body
    ) {
        $this->method = strtoupper($method);
        $allow = $this->implemented_methods;
        if (!in_array($this->method, $allow)) {
            $message = "Method '$this->method' is not implemented. "
                . 'Please use: ' . implode(', ', $allow);
            $this->sendError(
                static::ERROR_METHOD_NOT_IMPLEMENTED,
                $message,
                $allow
            );
        }
        $safe_method = in_array($this->method, ['GET', 'HEAD']);

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
            $data = $this->parseBody($type, $body);
            $resource['query'] = $query;
            $resource['data'] = $data;

            $methods = (array) ($resource_data['methods'] ?? null);
            if (!empty($methods)) {
                $allow = array_intersect(
                    $allow,
                    array_merge($methods, ['OPTIONS'])
                );
                if (!in_array($this->method, $allow)) {
                    $message = "Method '$this->method' is not allowed. "
                        . 'Please use: ' . implode(', ', $allow);
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
                    $resource_accept ?? $accept
                );

                $handlers = $resource_types[$accepted]['handler'];
                if (is_array($handlers)
                    && ($handlers[$resource['kind']] ?? null) === null
                ) {
                    $message = "Resource '$resource_name' can not generate "
                        . $resource['content_type'] . ' ' . $resource['kind'];
                    $this->sendError(static::ERROR_NOT_ACCEPTABLE, $message);
                }
            }

            if ($this->method !== 'OPTIONS') {
                $resource_obj = new Resource;
                foreach ($resource as $key => $value) {
                    $resource_obj->$key = $value;
                }

                $response = ($resource_obj->kind === 'collection')
                    ? $this->requestCollection($resource_obj)
                    : $this->requestResource($resource_obj);

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

        return $response;
    }

    /**
     * When requested route points to a collection
     *
     * @param Resource $resource Processed route
     *
     * @return Response
     * @return null     If response was sent by external handler
     */
    protected function requestCollection(Resource $resource)
    {
        $response = $this->prepareResponse();

        $where = $resource->where;
        $safe_method = in_array($this->method, ['GET', 'HEAD']);
        $resource_query = $resource->query;
        $fields = $this->parseFields($resource);

        $sort = $resource_query['sort'] ?? '';
        if ($sort !== '') {
            $sort = explode(',', $sort);
            $order = [];
            foreach ($sort as $id => $value) {
                if (strpos($value, '-') === 0) {
                    $sort[$id] = $value = substr($value, 1);
                    $order[$value] = 'DESC';
                } else {
                    $order[] = $value;
                }
            }
            $this->checkUnknownField($resource, $sort);
            $where['ORDER'] = $order;
        }

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

                    $result = $this->deleteModel($model, $resource, $route);
                    if ($result !== null) {
                        $list[] = Utils::arrayWhitelist($result, $fields);
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

                    $result = $this->updateModel($model, $resource, $route);
                    $body[] = Utils::arrayWhitelist($result, $fields);
                }
                break;

            case 'POST':
                $response->status = HttpResponse::HTTP_CREATED;
                $response->headers['Location'] = $this->createModel($resource);
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
     */
    protected function requestResource(Resource $resource)
    {
        $response = $this->prepareResponse();

        $resource_class = $resource->model_class;
        $fields = $this->parseFields($resource);

        if ($resource->exists) {
            $model = $resource_class::getInstance($resource->where);
        }

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

                $expand = $resource->query['expand'] ?? null;
                if ($expand === 'false'
                    || !$this->always_expand && $expand === null
                ) {
                    $body = $model->getData();

                    $routes = [];
                    foreach ($resource_class::FOREIGN_KEYS as $column => $fk) {
                        foreach ($this->resources as $res_name => $res_data) {
                            if ($res_data['model'] === $fk[0]) {
                                $foreign = $model->$column;
                                if ($foreign !== null) {
                                    $routes[$column] = "/$res_name/"
                                        . $this->getPrimaryKey($foreign);
                                }
                                break;
                            }
                        }
                    }
                    if (!empty($routes)) {
                        $response->headers['Link'] = $this->headerLink($routes);
                    }
                } else {
                    $body = $model->toArray();
                }

                $body = Utils::arrayWhitelist($body, $fields);
                break;

            case 'DELETE':
                $result = $this->deleteModel($model, $resource);
                if ($result !== null) {
                    $body = Utils::arrayWhitelist($result, $fields);
                }
                break;

            case 'PATCH':
                $result = $this->updateModel($model, $resource);
                $body = Utils::arrayWhitelist($result, $fields);
                break;

            case 'POST':
                $this->sendError(
                    static::ERROR_METHOD_NOT_ALLOWED,
                    'Resources do not allow POST Method'
                );
                break;

            case 'PUT':
                break;
        }

        if ($body !== null) {
            $response->headers['Content-Type'] = 'application/json';
            $response->body = $body;
        }

        return $response;
    }

    /**
     * When requested route is '/'
     *
     * @return Response With $this->meta and a row count for each resource
     */
    protected function requestRoot()
    {
        $count = [];
        foreach (array_keys($this->resources) as $resource) {
            $count[$resource] = $this->countResource($resource);
        }

        $response = $this->prepareResponse();
        $response->headers['Content-Type'] = 'application/json';
        $response->body = array_merge(
            $this->meta,
            [
                'resources' => $count,
            ]
        );

        return $response;
    }

    /*
     * Parsers
     * =========================================================================
     */

    /**
     * Parses request Accept
     *
     * NOTE:
     * - If $resource does not comply to $accept, but it does not forbid any of
     *   $resource's content types (i.e. ';q=0'), this function returns the
     *   first $resource's content type with highest priority. It is better to
     *   return something the user doesn't complain about than a useless error
     *
     * @param string $resource Resource name
     * @param string $accept   Request Accept
     *
     * @return string
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
                        $priority
                    );
                }
            } else {
                $priority -= 0.0001;
                foreach ($available_types as $resource_type => $value) {
                    if ($value > 0 && fnmatch($accept_type, $resource_type)) {
                        $list[$resource_type] = max(
                            $list[$resource_type] ?? 0,
                            $priority
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
     * @param string $body Request body
     *
     * @return array
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
     * It sends an error Response on failure
     *
     * @param Resource $resource Resource
     *
     * @return string[]
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
            $extension = pathinfo($route, PATHINFO_EXTENSION);
            if (array_key_exists($extension, $this->extensions)) {
                $route = substr($route, 0, (strlen($extension) + 1) * -1);
            } else {
                $extension = null;
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
                    ]
                );
            } else {
                if ($model === null) {
                    $where = @array_combine(
                        $resource_class::PRIMARY_KEY,
                        explode(
                            $this->primary_key_separator,
                            $id
                        )
                    );
                    if ($where === false) {
                        $this->sendError(
                            static::ERROR_INVALID_RESOURCE_ID,
                            "Invalid resource id for '$resource': '$id'"
                        );
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
     * It sends an error Response on failure
     *
     * @param Resource $resource Processed route
     *
     * @return string With route for new Model
     */
    protected function createModel(Resource $resource)
    {
        $model = new $resource->model_class;
        $model->fill($resource->data);

        if ($model->save()) {
            return "$this->url/$resource->name/" . $this->getPrimaryKey($model);
        }

        $code = (empty($resource->data))
            ? static::ERROR_INVALID_PAYLOAD
            : static::ERROR_INTERNAL_SERVER;

        $message = "Resource '$resource->name' could not be created";

        $this->sendError($code, $message);
    }

    /**
     * Deletes a Model
     *
     * It sends an error Response on failure
     *
     * @param Model    $model    Model to be deleted
     * @param Resource $resource Resource that loaded $model
     * @param string   $route    Alternative route to $model
     *
     * @return mixed[]|null
     */
    protected function deleteModel(
        Model $model,
        Resource $resource,
        string $route = null
    ) {
        if ($model->delete()) {
            return ($model::SOFT_DELETE !== null ? $model->getData() : null);
        }

        $message = "Resource '" . ($route ?? $resource->route)
            . "' could not be deleted";

        $this->sendError(static::ERROR_INTERNAL_SERVER, $message);
    }

    /**
     * Updates a Model
     *
     * It sends an error Response on failure
     *
     * @param Model    $model    Model to be updated
     * @param Resource $resource Resource that loaded $model
     * @param string   $route    Alternative route to $model
     *
     * @return mixed[]
     */
    protected function updateModel(
        Model $model,
        Resource $resource,
        string $route = null
    ) {
        $model->fill($resource->data);

        if ($model->update(array_keys($resource->data))) {
            return $model->getData();
        }

        $message = "Resource '" . ($route ?? $resource->route)
            . "' could not be updated";

        $this->sendError(static::ERROR_INTERNAL_SERVER, $message);
    }

    /*
     * Internal methods
     * =========================================================================
     */

    /**
     * Checks if a resource has all fields passed
     *
     * It sends an error Response on failure
     *
     * @param Resource $resource Resource
     * @param string[] $fields   List of fields to test
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
     * Creates a new Response object with some properties filled
     *
     * @return Response
     */
    protected function prepareResponse()
    {
        $response = new Response();
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
     * @throws RouteException With error Response
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

            case static::ERROR_METHOD_NOT_IMPLEMENTED:
                $status = HttpResponse::HTTP_NOT_IMPLEMENTED;
                $response->headers['Allow'] = $data;
                break;

            case static::ERROR_INVALID_RESOURCE:
            case static::ERROR_INVALID_RESOURCE_ID:
            case static::ERROR_INVALID_RESOURCE_OFFSET:
            case static::ERROR_INVALID_RESOURCE_FOREIGN:
            case static::ERROR_INVALID_PAYLOAD:
            case static::ERROR_NOT_ACCEPTABLE:
            case static::ERROR_INVALID_QUERY_PARAMETER:
            case static::ERROR_UNKNOWN_FIELDS:
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

            default:
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

        throw new RouteException($response);
    }
}
