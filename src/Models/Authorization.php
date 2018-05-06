<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\MedoolsRouter\Models;

use aryelgois\Medools;

/**
 * Holds user permissions for each resource
 *
 * @author Aryel Mota Góis
 * @license MIT
 * @link https://www.github.com/aryelgois/medools-router
 */
class Authorization extends Medools\Model
{
    const DATABASE = 'authentication';

    const TABLE = 'authorizations';

    const COLUMNS = [
        'user',
        'resource',
        'methods',
        'filter',
    ];

    const PRIMARY_KEY = [
        'user',
        'resource',
    ];

    const AUTO_INCREMENT = null;

    const OPTIONAL_COLUMNS = [
        'filter',
    ];

    const FOREIGN_KEYS = [
        'user' => [
            Authentication::class,
            'id'
        ],
    ];
}
