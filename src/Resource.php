<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\MedoolsRouter;

use aryelgois\Medools\ModelIterator;
use aryelgois\Medools\Exceptions\UnknownColumnException;

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
    public $name;

    /**
     * 'resource' or 'collection'
     *
     * @var string
     */
    public $kind;

    /**
     * Fully Qualified Class for a Medools Model
     *
     * @var string
     */
    public $model_class;

    /**
     * For loading or dumping model_class
     *
     * @var array
     */
    public $where;

    /**
     * If $where points to a existing row in $model_class
     *
     * It is only used if $kind is 'resource'
     *
     * @var boolean|null
     */
    public $exists;

    /**
     * Normalized and cleared route
     *
     * @var string
     */
    public $route;

    /**
     * Direct route to Resource
     *
     * It is only used if $kind is 'resource' and the original route contained
     * nested resources
     *
     * @var string|null
     */
    public $content_location;

    /**
     * Known extension
     *
     * @var string|null
     */
    public $extension;

    /**
     * Parsed query
     *
     * @var array
     */
    public $query;

    /**
     * Parsed Content Body
     *
     * @var array
     */
    public $data;

    /**
     * Which content type should be produced, null means internal content type
     *
     * @var string
     */
    public $content_type;

    /**
     * Contains preprocessed data
     *
     * @var mixed[]
     */
    protected $cache;

    /**
     * Returns quered fields
     *
     * It supports special fields that expand to others
     *
     * @return string[] On success
     * @return string   On failure, with error message
     */
    public function getFields()
    {
        $query_fields = $this->query['fields'] ?? '';
        if ($query_fields === '') {
            return [];
        }

        $special = $this->getSpecialFields();
        foreach ($special as $key => $value) {
            $special[$key] = implode(',', $value);
        }

        $fields = array_filter(explode(',', str_replace(
            array_keys($special),
            $special,
            $query_fields
        )));

        $message = $this->hasFields($fields);
        if ($message !== true) {
            return $message;
        }
        return $fields;
    }

    /**
     * Returns a model Iterator
     *
     * @return ModelIterator
     */
    public function getIterator()
    {
        return new ModelIterator($this->model_class, $this->where);
    }

    /**
     * Returns a list of arrays to load models
     *
     * @return array[]
     */
    public function getList()
    {
        if ($this->kind === 'collection') {
            $model_class = $this->model_class;
            return $model_class::dump($this->where, $model_class::PRIMARY_KEY);
        }
        return [$this->where];
    }

    /**
     * Returns map of special fields
     *
     * @return array[]
     */
    public function getSpecialFields()
    {
        $cached = $this->cache['special_fields'] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $model = $this->model_class;
        $special = [
            'PRIMARY_KEY' => $model::PRIMARY_KEY,
            'AUTO_INCREMENT' => (array) $model::AUTO_INCREMENT,
            'STAMP_COLUMNS' => $model::STAMP_COLUMNS,
            'OPTIONAL_COLUMNS' => $model::OPTIONAL_COLUMNS,
            'FOREIGN_KEYS' => array_keys($model::FOREIGN_KEYS),
            'SOFT_DELETE' => (array) $model::SOFT_DELETE,
        ];

        $this->cache['special_fields'] = $special;
        return $special;
    }

    /**
     * Checks if Resource has all fields passed
     *
     * @param string[] $fields List of fields to test
     *
     * @return true   On success
     * @return string On failure, with error message
     */
    public function hasFields(array $fields)
    {
        try {
            $this->model_class::checkUnknownColumn($fields);
        } catch (UnknownColumnException $e) {
            return "Resource '$this->name' "
                . explode(' ', $e->getMessage(), 2)[1];
        }
        return true;
    }
}
