<?php
/**
 * This Software is part of aryelgois/medools-router and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\MedoolsRouter\Models;

use aryelgois\Medools;

/**
 * Provides Basic Authentication
 *
 * @author Aryel Mota Góis
 * @license MIT
 * @link https://www.github.com/aryelgois/medools-router
 */
class Authentication extends Medools\Model
{
    const DATABASE = 'authentication';

    const TABLE = 'authentications';

    const COLUMNS = [
        'id',
        'username',
        'password',
        'email',
        'verified',
        'enabled',
        'update',
        'stamp',
    ];

    const OPTIONAL_COLUMNS = [
        'verified',
    ];

    const STAMP_COLUMNS = [
        'stamp' => 'auto',
        'update' => 'auto',
    ];

    const SOFT_DELETE = 'enabled';

    const SOFT_DELETE_MODE = 'active';

    /**
     * Hash algorithm to be applied in the password
     *
     * @see http://php.net/password-hash
     *
     * @var integer
     */
    const PASSWORD_HASH = PASSWORD_DEFAULT;

    /**
     * Checks if password matches
     *
     * @param string $password Plain data to be tested
     *
     * @return boolean For success or failure
     */
    public function checkPassword($password)
    {
        if ($this->isFresh()) {
            return false;
        }

        return password_verify($password, $this->password);
    }

    /**
     * Hashes Password
     *
     * @param string $password Plain data to be hashed
     *
     * @return string On success
     * @return false  On failure
     */
    public static function hashPassword($password)
    {
        return password_hash($password, static::PASSWORD_HASH);
    }

    /**
     * Called when a column is changed
     *
     * @return mixed New column value
     */
    protected function onColumnChange($column, $value)
    {
        if ($column === 'password') {
            $value = static::hashPassword($value);
        }

        return $value;
    }
}
