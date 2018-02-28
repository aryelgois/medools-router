<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\Medools\Router;

/**
 * Holds data processed from a route
 *
 * @author Aryel Mota Góis
 * @license MIT
 * @link https://www.github.com/aryelgois/medools-router
 */
class Resource
{
    /**
     * Key for Router->resources
     *
     * @var string
     */
    protected $name;

    /**
     * 'resource' or 'collection'
     *
     * @var string
     */
    protected $type;

    /**
     * Fully Qualified Class name for a Medools Model
     *
     * @var string
     */
    protected $model_class;

    /**
     * For loading or dumping Model
     *
     * @var array
     */
    protected $where;

    /**
     * Normalized and cleared route
     *
     * @var string
     */
    protected $route;

    /**
     * Known extension or null
     *
     * @var string
     */
    protected $extension;

    /**
     * Creates a new Resource object
     *
     * @param string $name      Key for Router->resources
     * @param string $type      'resource' or 'collection'
     * @param string $model     Fully Qualified Class name for a Medools Model
     * @param array  $where     For loading or dumping Model
     * @param string $route     Normalized and cleared route
     * @param string $extension Known extension or null
     */
    public function __construct(
        string $name,
        string $type,
        string $model_class,
        array $where,
        string $route,
        string $extension = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->model_class = $model_class;
        $this->where = $where;
        $this->route = $route;
        $this->extension = $extension;
    }

    /**
     * Retireves stored data
     *
     * @param string $property A valid property
     *
     * @return mixed
     *
     * @throws \DomainException If $property is invalid
     */
    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->{$property};
        } else {
            $message = "Resource does not have '$property' property";
            throw new \DomainException($message);
        }
    }

    /**
     * Returns a list of model ids
     *
     * @return array
     */
    public function getList()
    {
        if ($this->type === 'collection') {
            $model_class = $this->model_class;
            return $model_class::dump($this->where, $model_class::PRIMARY_KEY);
        }
        return [$this->where];
    }
}
