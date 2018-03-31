<?php

require_once __DIR__ . '/../../bootstrap.php';

use aryelgois\MedoolsRouter;

// Request
$headers = getallheaders();

// Router
$url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

$router_data = json_decode(file_get_contents(__DIR__ . '/../router.json'), true);

$controller = new MedoolsRouter\Controller(
    $url,
    $router_data['resources'],
    $router_data['configurations']
);

$controller->authenticate($headers['Authorization'] ?? '');
