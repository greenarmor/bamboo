<?php

use Bamboo\Swoole\ServerInstrumentation;
use Bamboo\Web\Health\HealthState;
use OpenSwoole\Exception as OpenSwooleException;
use OpenSwoole\Runtime;

if (extension_loaded('openswoole') && class_exists(Runtime::class)) {
  if (defined('SWOOLE_HOOK_ALL')) {
    Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);
  } else {
    Runtime::enableCoroutine(true);
  }
}

$app = require __DIR__ . '/app.php';
$health = $app->has(HealthState::class) ? $app->get(HealthState::class) : null;

$host = $app->config('server.host');
$port = (int)$app->config('server.port');
$server = null;

try {
  $server = new OpenSwoole\HTTP\Server($host, $port);
} catch (Throwable $e) {
  $message = $e->getMessage();

  if ($e instanceof OpenSwooleException && str_contains(strtolower($message), 'address already in use')) {
    $message = 'Address already in use.';
  } else {
    $message = sprintf('Failed to bind to %s:%s. %s', $host, $port, $message);
  }

  fwrite(STDERR, $message . PHP_EOL . 'Stack trace:' . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
  exit(1);
}

/** @var OpenSwoole\HTTP\Server $server */
ServerInstrumentation::record($server, $host, $port);

$server->set([
  'document_root' => dirname(__DIR__) . '/public',
  'enable_static_handler' => $app->config('server.static_enabled'),
  'worker_num' => $app->config('server.workers'),
  'task_worker_num' => $app->config('server.task_workers'),
  'max_request' => $app->config('server.max_requests'),
]);

$server->on('start', ServerInstrumentation::registerListener('start', function () use ($host, $port, $health): void {
  ServerInstrumentation::markStarted();
  if ($health) {
    $health->markReady();
  }
  echo "Bamboo HTTP online at http://{$host}:{$port}\n";
}));

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

if ($health) {
  $server->on('workerStop', ServerInstrumentation::registerListener('workerStop', function () use ($health): void {
    $health->markShuttingDown();
  }));

  $server->on('shutdown', ServerInstrumentation::registerListener('shutdown', function () use ($health): void {
    $health->markShuttingDown();
  }));
}

$disableStart = filter_var($_ENV['DISABLE_HTTP_SERVER_START'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($disableStart) {
  ServerInstrumentation::trigger('start');
  return;
}

$server->start();
