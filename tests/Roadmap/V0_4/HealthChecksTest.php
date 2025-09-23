<?php

namespace Tests\Roadmap\V0_4;

use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Provider\AppProvider;
use Bamboo\Provider\MetricsProvider;
use Bamboo\Provider\ResilienceProvider;
use Bamboo\Web\Health\HealthState;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class HealthChecksTest extends TestCase {
  protected function setUp(): void {
    parent::setUp();
    HealthState::resetGlobal();
    $_ENV['LOG_FILE'] = 'php://temp';
    $_ENV['REDIS_URL'] = 'memory://local';
  }

  protected function tearDown(): void {
    HealthState::resetGlobal();
    parent::tearDown();
  }

  public function testHealthzEndpointReportsLiveness(): void {
    $app = $this->createApp();

    $response = $app->handle(new ServerRequest('GET', '/healthz'));
    $payload = json_decode((string) $response->getBody(), true);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['live']);
    $this->assertTrue($payload['ready']);
    $this->assertSame('live', $payload['status']);
  }

  public function testReadyzEndpointReflectsDependencyStatus(): void {
    $app = $this->createApp();
    $state = $app->get(HealthState::class);

    $state->setDependency('redis', true);
    $response = $app->handle(new ServerRequest('GET', '/readyz'));
    $payload = json_decode((string) $response->getBody(), true);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('ready', $payload['status']);

    $state->setDependency('database', false, 'connection refused');
    $response = $app->handle(new ServerRequest('GET', '/readyz'));
    $payload = json_decode((string) $response->getBody(), true);

    $this->assertSame(503, $response->getStatusCode());
    $this->assertSame('not_ready', $payload['status']);
    $this->assertFalse($payload['dependencies']['database']['healthy']);
    $this->assertSame('connection refused', $payload['dependencies']['database']['message']);
  }

  public function testGracefulShutdownMarksWorkerUnreadyBeforeExit(): void {
    HealthState::resetGlobal();
    $_ENV['DISABLE_HTTP_SERVER_START'] = 'true';

    require dirname(__DIR__, 3) . '/bootstrap/server.php';

    $state = HealthState::global();
    $this->assertInstanceOf(HealthState::class, $state);
    $this->assertTrue($state->isReady());

    $server = \OpenSwoole\HTTP\Server::$lastInstance;
    $this->assertNotNull($server);
    $server->trigger('shutdown');

    $this->assertFalse($state->isReady());

    unset($_ENV['DISABLE_HTTP_SERVER_START']);
    \OpenSwoole\HTTP\Server::$lastInstance = null;
  }

  private function createApp(): Application {
    $config = new Config(dirname(__DIR__, 3) . '/etc');
    $app = new Application($config);
    $app->register(new AppProvider());
    $app->register(new MetricsProvider());
    $app->register(new ResilienceProvider());

    return $app;
  }
}
