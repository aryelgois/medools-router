<?php

require_once __DIR__ . '/../../bootstrap.php';

use aryelgois\MedoolsRouter;

$request = from_globals();

$router_data = json_decode(file_get_contents(__DIR__ . '/../router.json'), true);

$controller = new MedoolsRouter\Controller(
    $request['url'],
    $router_data['resources'],
    $router_data['configurations']
);

$controller->authenticate($request['headers']['Authorization'] ?? '');
