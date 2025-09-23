<?php

namespace Tests\Roadmap\V0_4;

use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Provider\AppProvider;
use Bamboo\Provider\MetricsProvider;
use Bamboo\Provider\ResilienceProvider;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Prometheus\RenderTextFormat;

class MetricsEndpointTest extends TestCase {
  protected function setUp(): void {
    parent::setUp();
    $_ENV['LOG_FILE'] = 'php://temp';
    $_ENV['REDIS_URL'] = 'memory://local';
  }

  public function testMetricsEndpointExposesPrometheusTextFormat(): void {
    $app = $this->createApp();

    $response = $app->handle(new ServerRequest('GET', '/metrics'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(RenderTextFormat::MIME_TYPE, $response->getHeaderLine('Content-Type'));
    $this->assertStringContainsString('# HELP', (string) $response->getBody());
  }

  public function testMetricsEndpointReturns503WhenCollectorUnavailable(): void {
    $app = $this->createApp();

    $remover = \Closure::bind(function(string $id): void {
      unset($this->bindings[$id], $this->instances[$id]);
    }, $app, Application::class);

    $remover(RenderTextFormat::class);

    $response = $app->handle(new ServerRequest('GET', '/metrics'));

    $this->assertSame(503, $response->getStatusCode());
    $this->assertSame('metrics collection unavailable: dependencies missing', trim((string) $response->getBody()));
  }

  private function createApp(): Application {
    $config = new Config(dirname(__DIR__, 3) . '/etc');
    $app = new Application($config);
    $app->register(new AppProvider());
    $app->register(new ResilienceProvider());
    $app->register(new MetricsProvider());

    return $app;
  }
}
