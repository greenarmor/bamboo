<?php
$app = require __DIR__ . '/../bootstrap/app.php';
$psr17 = new Http\Factory\Guzzle\ServerRequestFactory();
$request = $psr17->createServerRequest($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
$response = $app->handle($request);
Bamboo\Core\ResponseEmitter::emitCli($response);
