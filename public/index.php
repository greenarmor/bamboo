<?php
$app = require __DIR__ . '/../bootstrap/app.php';
$psr17 = new Http\Factory\Guzzle\ServerRequestFactory();
$request = $psr17->createServerRequest($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
try {
  $response = $app->handle($request);
} catch (Throwable $e) {
  $handler = $app->get(Bamboo\Web\ProblemDetailsHandler::class);
  $response = $handler->handle($e, $request);
}
Bamboo\Core\ResponseEmitter::emitCli($response);
