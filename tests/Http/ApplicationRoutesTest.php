<?php

namespace Tests\Http;

use Bamboo\Provider\AppProvider;
use Bamboo\Provider\MetricsProvider;
use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Prometheus\RenderTextFormat;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\PredisFakeServer;
use Tests\Stubs\PredisMemoryConnection;

class ApplicationRoutesTest extends TestCase {
  protected function setUp(): void {
    parent::setUp();
    PredisFakeServer::reset();
    $_ENV['LOG_FILE'] = 'php://temp';
    $_ENV['REDIS_URL'] = 'memory://local';
  }

  private function createApp(): Application {
    $config = new Config(dirname(__DIR__, 2) . '/etc');
    $app = new Application($config);
    $app->register(new AppProvider());
    $app->register(new MetricsProvider());
    $app->register(new \Bamboo\Provider\ResilienceProvider());
    $app->singleton('redis.client.factory', function() use ($app) {
      return function(array $overrides = []) use ($app) {
        $config = array_replace($app->config('redis') ?? [], $overrides);
        $url = $config['url'] ?? 'memory://local';
        $options = $config['options'] ?? [];
        $options['connections']['memory'] = PredisMemoryConnection::factory();
        return new \Predis\Client($url, $options);
      };
    });
    return $app;
  }

  public function testHomeRouteRespondsWithFrameworkMetadata(): void {
    $app = $this->createApp();
    $response = $app->handle(new ServerRequest('GET', '/'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $data = json_decode((string) $response->getBody(), true);
    $this->assertIsArray($data);
    $this->assertSame('Bamboo', $data['framework']);
    $this->assertArrayHasKey('php', $data);
    $this->assertArrayHasKey('time', $data);
  }

  public function testHelloRouteGreetsName(): void {
    $app = $this->createApp();
    $response = $app->handle(new ServerRequest('GET', '/hello/Bamboo'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('Hello, Bamboo!' . "\n", (string) $response->getBody());
    $this->assertSame('text/plain', $response->getHeaderLine('Content-Type'));
  }

  public function testEchoRouteReturnsPostedJson(): void {
    $app = $this->createApp();
    $psr17 = new Psr17Factory();
    $body = $psr17->createStream(json_encode(['ok' => true], JSON_THROW_ON_ERROR));
    $request = (new ServerRequest('POST', '/api/echo'))->withBody($body);
    $response = $app->handle($request);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $this->assertSame(['ok' => true], json_decode((string) $response->getBody(), true));
  }

  public function testJobRouteEnqueuesPayload(): void {
    $app = $this->createApp();
    $psr17 = new Psr17Factory();
    $payload = json_encode(['task' => 'demo'], JSON_THROW_ON_ERROR);
    $request = (new ServerRequest('POST', '/api/jobs'))->withBody($psr17->createStream($payload));

    $response = $app->handle($request);

    $this->assertSame(202, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $this->assertSame(['queued' => true], json_decode((string) $response->getBody(), true));
    $queue = PredisFakeServer::dumpQueue('jobs');
    $this->assertCount(1, $queue);
    $this->assertSame(['task' => 'demo'], json_decode($queue[0], true));
  }

  public function testClosureRouteWithSingleParameterReceivesRequest(): void {
    $app = $this->createApp();
    $router = $app->get('router');
    $capturedRequest = null;

    $router->get('/test/single', function(ServerRequest $request) use (&$capturedRequest) {
      $capturedRequest = $request;
      return new Response(200, ['Content-Type' => 'text/plain'], 'ok');
    });

    $request = new ServerRequest('GET', '/test/single');
    $response = $app->handle($request);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertInstanceOf(ServerRequest::class, $capturedRequest);
    $this->assertSame('GET', $capturedRequest->getMethod());
    $this->assertSame('/test/single', $capturedRequest->getUri()->getPath());
  }

  public function testClosureRouteWithTwoParametersReceivesRequestAndVars(): void {
    $app = $this->createApp();
    $router = $app->get('router');
    $capturedRequest = null;
    $capturedVars = null;

    $router->get('/test/two/{value}', function(ServerRequest $request, array $vars) use (&$capturedRequest, &$capturedVars) {
      $capturedRequest = $request;
      $capturedVars = $vars;
      return new Response(200, ['Content-Type' => 'application/json'], json_encode(['value' => $vars['value'] ?? null], JSON_THROW_ON_ERROR));
    });

    $request = new ServerRequest('GET', '/test/two/demo');
    $response = $app->handle($request);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $this->assertSame(['value' => 'demo'], json_decode((string) $response->getBody(), true));
    $this->assertSame($request->getUri()->getPath(), $capturedRequest->getUri()->getPath());
    $this->assertSame(['value' => 'demo'], $capturedVars);
  }

  public function testMetricsRouteRendersPrometheusTextFormat(): void {
    $app = $this->createApp();
    $response = $app->handle(new ServerRequest('GET', '/metrics'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(RenderTextFormat::MIME_TYPE, $response->getHeaderLine('Content-Type'));

    $body = (string) $response->getBody();
    $this->assertNotSame('', $body);
    $this->assertStringContainsString('# HELP bamboo_http_requests_in_flight', $body);
    $this->assertStringContainsString('bamboo_http_requests_in_flight', $body);
  }
}
