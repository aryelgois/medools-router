<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\Medools\Router;

use aryelgois\Utils;

/**
 * Holds data processed from a route
 *
 * @author Aryel Mota Góis
 * @license MIT
 * @link https://www.github.com/aryelgois/medools-router
 */
class Resource extends Utils\ReadOnly
{
    const KEYS = [
        'name'         => 'string', // Key for Router->resources
        'type'         => 'string', // 'resource' or 'collection'
        'model_class'  => 'string', // Fully Qualified Class for a Medools Model
        'where'        => 'array',  // For loading or dumping model_class
        'route'        => 'string', // Normalized and cleared route
        'extension'    => 'string', // Known extension
        'query'        => 'array',  // Parsed query
        'data'         => 'array',  // Parsed Content Body
        'content_type' => 'string', // Which content type should be produced,
                                    // null means internal content type
    ];

    const OPTIONAL = ['extension', 'content_type'];

    /**
     * Returns a list of model ids
     *
     * @return array[]
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
