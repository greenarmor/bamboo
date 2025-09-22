<?php

namespace Tests\Http;

use Bamboo\Provider\AppProvider;
use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
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
    $body = $psr17->createStream(json_encode(['ok' => true]));
    $request = (new ServerRequest('POST', '/api/echo'))->withBody($body);
    $response = $app->handle($request);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $this->assertSame(['ok' => true], json_decode((string) $response->getBody(), true));
  }

  public function testJobRouteEnqueuesPayload(): void {
    $app = $this->createApp();
    $psr17 = new Psr17Factory();
    $payload = json_encode(['task' => 'demo']);
    $request = (new ServerRequest('POST', '/api/jobs'))->withBody($psr17->createStream($payload));

    $response = $app->handle($request);

    $this->assertSame(202, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $this->assertSame(['queued' => true], json_decode((string) $response->getBody(), true));
    $queue = PredisFakeServer::dumpQueue('jobs');
    $this->assertCount(1, $queue);
    $this->assertSame(['task' => 'demo'], json_decode($queue[0], true));
  }
}
