<?php

namespace Tests\Console;

use Bamboo\Console\Command\HttpServe;
use Bamboo\Swoole\ServerInstrumentation;
use OpenSwoole\HTTP\Server;
use PHPUnit\Framework\TestCase;
use Tests\Support\PortAllocator;
use Tests\Support\RouterTestApplication;

class HttpServeCommandTest extends TestCase {
  private int $port;

  protected function setUp(): void {
    parent::setUp();
    ServerInstrumentation::reset();
    $_ENV['LOG_FILE'] = 'php://temp';
    $_ENV['DISABLE_HTTP_SERVER_START'] = 'true';
    $this->port = PortAllocator::allocate();
    $_ENV['HTTP_PORT'] = (string)$this->port;
  }

  protected function tearDown(): void {
    unset($_ENV['DISABLE_HTTP_SERVER_START']);
    unset($_ENV['HTTP_PORT']);
    parent::tearDown();
  }

  public function testHandleBootstrapsServer(): void {
    $command = new HttpServe(new RouterTestApplication());

    ob_start();
    $exitCode = $command->handle([]);
    $output = ob_get_clean();

    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString('Bamboo HTTP online', $output);

    $server = ServerInstrumentation::server();
    $this->assertInstanceOf(Server::class, $server);
    $this->assertTrue(ServerInstrumentation::started());
    $this->assertSame('127.0.0.1', ServerInstrumentation::host());
    $this->assertSame($this->port, ServerInstrumentation::port());
    $this->assertStringContainsString((string) $this->port, $output);
  }
}
