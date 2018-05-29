<?php

// debug
ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

// autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Medools
aryelgois\Medools\MedooConnection::loadConfig(__DIR__ . '/../config/medools.php');

/**
 * Returns data used by MedoolsRouter from Global variables
 *
 * @return array
 */
function from_globals()
{
    $script_dir = dirname($_SERVER['PHP_SELF']);

    $uri = $_SERVER['REQUEST_URI'];
    if ($script_dir !== '/') {
        $uri = substr_replace($uri, '', 0, strlen($script_dir));
    }

    return [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $uri,
        'headers' => getallheaders(),
        'body' => file_get_contents('php://input'),
        'url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $script_dir,
    ];
}
