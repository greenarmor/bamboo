<?php

namespace Tests\Console;

use Bamboo\Console\Command\HttpServe;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouterTestApplication;

class HttpServeCommandTest extends TestCase {
  protected function setUp(): void {
    parent::setUp();
    \OpenSwoole\HTTP\Server::$lastInstance = null;
    $_ENV['LOG_FILE'] = 'php://temp';
  }

  public function testHandleBootstrapsServer(): void {
    $command = new HttpServe(new RouterTestApplication());

    ob_start();
    $exitCode = $command->handle([]);
    $output = ob_get_clean();

    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString('Bamboo HTTP online', $output);

    $server = \OpenSwoole\HTTP\Server::$lastInstance;
    $this->assertNotNull($server);
    $this->assertTrue($server->started);
    $this->assertSame('127.0.0.1', $server->host);
    $this->assertSame(9501, $server->port);
  }
}
