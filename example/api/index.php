<?php

require_once __DIR__ . '/../bootstrap.php';

use aryelgois\MedoolsRouter;

// Request
$method = $_SERVER['REQUEST_METHOD'];

$uri = str_replace(dirname($_SERVER['PHP_SELF']), '', $_SERVER['REQUEST_URI']);

$headers = getallheaders();

$body = file_get_contents('php://input');

// Router
$url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

$router_data = json_decode(file_get_contents(__DIR__ . '/router.json'), true);

$controller = new MedoolsRouter\Controller(
    $url,
    $router_data['resources'],
    $router_data['configurations']
);

$controller->run($method, $uri, $headers, $body);
