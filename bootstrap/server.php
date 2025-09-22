<?php

use Bamboo\Swoole\ServerInstrumentation;

$app = require __DIR__ . '/app.php';

$host = $app->config('server.host');
$port = (int)$app->config('server.port');
$server = new OpenSwoole\HTTP\Server($host, $port);
ServerInstrumentation::record($server, $host, $port);

$server->set([
  'document_root' => dirname(__DIR__) . '/public',
  'enable_static_handler' => $app->config('server.static_enabled'),
  'worker_num' => $app->config('server.workers'),
  'task_worker_num' => $app->config('server.task_workers'),
  'max_request' => $app->config('server.max_requests'),
]);

$server->on('start', function() use ($host,$port){
  ServerInstrumentation::markStarted();
  echo "Bamboo HTTP online at http://{$host}:{$port}\n";
});

$server->on('request', function(OpenSwoole\HTTP\Request $req, OpenSwoole\HTTP\Response $res) use ($app) {
  $psr = Bamboo\Core\Helpers::toPsrRequest($req);
  try {
    $response = $app->handle($psr);
  } catch (Throwable $e) {
    $handler = $app->get(Bamboo\Web\ProblemDetailsHandler::class);
    $response = $handler->handle($e, $psr);
  }
  Bamboo\Core\ResponseEmitter::emit($res, $response);
});

$server->on('task', function (OpenSwoole\Server $server, int $taskId, int $srcWorkerId, mixed $data): void {
  // Task workers are optional; immediately acknowledge work when present.
});

$server->on('finish', function (OpenSwoole\Server $server, int $taskId, mixed $data): void {
  // No-op finish handler keeps OpenSwoole satisfied when task workers are enabled.
});

$disableStart = filter_var($_ENV['DISABLE_HTTP_SERVER_START'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($disableStart) {
  ServerInstrumentation::markStarted();
  echo "Bamboo HTTP online at http://{$host}:{$port}\n";
  return;
}

$server->start();
