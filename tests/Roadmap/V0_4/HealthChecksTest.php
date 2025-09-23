<?php

namespace Tests\Roadmap\V0_4;

use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Provider\AppProvider;
use Bamboo\Provider\MetricsProvider;
use Bamboo\Provider\ResilienceProvider;
use Bamboo\Swoole\ServerInstrumentation;
use Bamboo\Web\Health\HealthState;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Tests\Support\OpenSwooleCompat;
use Tests\Support\PortAllocator;

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
    if (!OpenSwooleCompat::httpServerUsesStub()) {
      $this->markTestSkipped('OpenSwoole test doubles are required to dispatch shutdown events.');
    }

    HealthState::resetGlobal();
    $_ENV['DISABLE_HTTP_SERVER_START'] = 'true';
    $_ENV['HTTP_PORT'] = (string)PortAllocator::allocate();
    ServerInstrumentation::reset();

      try {
        require dirname(__DIR__, 3) . '/bootstrap/server.php';
      } catch (\OpenSwoole\Exception $e) {
        $this->markTestSkipped('Unable to create OpenSwoole HTTP server: ' . $e->getMessage());
      }

    $state = HealthState::global();
    $this->assertInstanceOf(HealthState::class, $state);
    $this->assertTrue($state->isReady());

    $server = ServerInstrumentation::server();
    $this->assertNotNull($server);

    $server->trigger('shutdown');

    $this->assertFalse($state->isReady());

    unset($_ENV['DISABLE_HTTP_SERVER_START']);
    unset($_ENV['HTTP_PORT']);

    if (OpenSwooleCompat::httpServerUsesStub()) {
      \OpenSwoole\HTTP\Server::$lastInstance = null;
    }

    ServerInstrumentation::reset();
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
