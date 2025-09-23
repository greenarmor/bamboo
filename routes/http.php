<?php

use Bamboo\Core\RouteDefinition;

/** @var Bamboo\Core\Application $this */

/** @var Bamboo\Core\Router $router */
$router = $this->get('router');

$router->get('/', RouteDefinition::forHandler(
  [Bamboo\Web\Controller\Home::class, 'index'],
  middlewareGroups: ['web'],
));

$router->get('/metrics', RouteDefinition::forHandler(
  [Bamboo\Web\Controller\MetricsController::class, 'index']
));

$router->get('/healthz', RouteDefinition::forHandler(
  [Bamboo\Web\Controller\HealthController::class, 'healthz']
));

$router->get('/readyz', RouteDefinition::forHandler(
  [Bamboo\Web\Controller\HealthController::class, 'readyz']
));

$router->get('/hello/{name}', RouteDefinition::forHandler(
  function($request, $vars){
    return new Nyholm\Psr7\Response(200, ['Content-Type'=>'text/plain'], "Hello, {$vars['name']}!\n");
  },
  middleware: [Bamboo\Web\Middleware\SignatureHeader::class],
));

$router->post('/api/echo', function($request){
  $body = (string)$request->getBody();
  return new Nyholm\Psr7\Response(200, ['Content-Type'=>'application/json'], $body ?: '{}');
}, [Bamboo\Web\Middleware\SignatureHeader::class]);

// Client API demo: concurrent GETs against httpbin
$router->get('/api/httpbin', function() {
  /** @var \Bamboo\Http\Client $http */
  $http = $this->get('http.client');
  $client = $http->for('httpbin');

  $psr17 = new Nyholm\Psr7\Factory\Psr17Factory();
  $req1 = $psr17->createRequest('GET', '/get?i=1');
  $req2 = $psr17->createRequest('GET', '/get?i=2');

  $responses = $client->sendConcurrent([$req1, $req2]);

  return new Nyholm\Psr7\Response(
    200, ['Content-Type'=>'application/json'],
    json_encode([
      'results' => array_map(fn($r) => json_decode((string)$r->getBody(), true), $responses)
    ])
  );
});

// Queue enqueue
$router->post('/api/jobs', function($request) {
  $cfg = $this->get(Bamboo\Core\Config::class)->get('redis');
  $factory = $this->get('redis.client.factory');
  $r = $factory();
  $payload = (string)$request->getBody();
  $r->rpush($cfg['queue'], [$payload ?: json_encode(['job'=>'noop'])]);
  return new Nyholm\Psr7\Response(202, ['Content-Type'=>'application/json'], json_encode(['queued'=>true]));
});
