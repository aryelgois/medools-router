<?php
/**
 * Example of Configuration for MedooConnection
 *
 * @see https://github.com/aryelgois/Medools#setup for details
 */

return [
    'servers' => [
        'default' => [
            // required
            'server' => 'localhost',
            'username' => 'root',
            'password' => 'password',
            'database_type' => 'mysql',

            // [optional]
            'charset' => 'utf8',
        ],
    ],
    'databases' => [
        'default' => [
            'database_name' => 'my_database',
        ],

        /*
         * Database for authentication
         *
         * You can set the database_name value to the same as your default
         * database, just remember to create the authentication.yml database
         * with a different database name
         */
        'authentication' => [
            'database_name' => 'authentication',
        ],
    ],
];
