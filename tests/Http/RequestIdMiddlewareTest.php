<?php

namespace Tests\Http;

use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Core\RouteDefinition;
use Bamboo\Provider\AppProvider;
use Bamboo\Web\RequestContext;
use Monolog\Handler\TestHandler;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class RequestIdMiddlewareTest extends TestCase {
  protected function setUp(): void {
    parent::setUp();
    $_ENV['LOG_FILE'] = 'php://temp';
  }

  private function createApp(): Application {
    $config = new Config(dirname(__DIR__, 2) . '/etc');
    $app = new Application($config);
    $app->register(new AppProvider());
    return $app;
  }

  public function testPropagatesInboundRequestId(): void {
    $app = $this->createApp();
    $captured = [];
    $app->get('router')->get('/context', RouteDefinition::forHandler(function($request) use (&$captured) {
      $captured['request_id'] = $request->getAttribute('request_id');
      $captured['correlation_id'] = $request->getAttribute('correlation_id');
      return new Response(204);
    }, middlewareGroups: ['web']));

    $request = (new ServerRequest('GET', '/context'))->withHeader('X-Request-ID', 'abc-123');
    $response = $app->handle($request);

    $this->assertSame('abc-123', $response->getHeaderLine('X-Request-ID'));
    $this->assertSame('abc-123', $captured['request_id']);
    $this->assertSame('abc-123', $captured['correlation_id']);
    $context = $app->get(RequestContext::class);
    $this->assertSame('abc-123', $context->get('id'));
    $this->assertSame('GET /context', $context->get('route'));
    $this->assertSame('fast', $response->getHeaderLine('X-Bamboo'));
  }

  public function testGeneratedRequestIdAppearsInLoggerContext(): void {
    $app = $this->createApp();
    $app->get('router')->get('/logger', fn() => new Response(200, [], 'ok'), [], ['web']);

    $response = $app->handle(new ServerRequest('GET', '/logger'));
    $generatedId = $response->getHeaderLine('X-Request-ID');
    $this->assertNotSame('', $generatedId);

    $logger = $app->get('log');
    $handler = new TestHandler();
    $logger->setHandlers([$handler]);
    $logger->info('testing');

    $this->assertTrue($handler->hasInfoRecords());
    $records = $handler->getRecords();
    $this->assertSame($generatedId, $records[0]['extra']['request']['id'] ?? null);
    $this->assertSame('GET', $records[0]['extra']['request']['method'] ?? null);
    $this->assertSame('GET /logger', $records[0]['extra']['request']['route'] ?? null);
  }

  public function testConcurrentRequestsReceiveDistinctIds(): void {
    $app = $this->createApp();
    $app->get('router')->get('/unique', fn() => new Response(200, [], 'ok'), [], ['web']);

    $first = $app->handle(new ServerRequest('GET', '/unique'));
    $second = $app->handle(new ServerRequest('GET', '/unique'));

    $firstId = $first->getHeaderLine('X-Request-ID');
    $secondId = $second->getHeaderLine('X-Request-ID');

    $this->assertNotSame('', $firstId);
    $this->assertNotSame('', $secondId);
    $this->assertNotSame($firstId, $secondId);
  }
}
