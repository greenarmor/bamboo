<?php

namespace Tests\Console;

use Bamboo\Console\Command\HttpServe;
use Bamboo\Swoole\ServerInstrumentation;
use OpenSwoole\HTTP\Server;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouterTestApplication;

class HttpServeCommandTest extends TestCase {
  protected function setUp(): void {
    parent::setUp();
    ServerInstrumentation::reset();
    $_ENV['LOG_FILE'] = 'php://temp';
    $_ENV['DISABLE_HTTP_SERVER_START'] = 'true';
  }

  protected function tearDown(): void {
    unset($_ENV['DISABLE_HTTP_SERVER_START']);
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
    $this->assertSame(9501, ServerInstrumentation::port());
  }
}
