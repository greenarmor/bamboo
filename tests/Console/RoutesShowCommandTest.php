<?php

namespace Tests\Console;

use Bamboo\Console\Command\RoutesShow;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouterTestApplication;

class RoutesShowCommandTest extends TestCase {
  public function testDisplaysRegisteredRoutes(): void {
    $app = new RouterTestApplication([
      ['GET', '/users', [RoutesShowController::class, 'index']],
      ['POST', '/jobs', fn() => 'ok'],
    ]);

    $command = new RoutesShow($app);

    ob_start();
    $exitCode = $command->handle([]);
    $output = ob_get_clean();

    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString('GET    /users', $output);
    $this->assertStringContainsString(RoutesShowController::class . '@index', $output);
    $this->assertStringContainsString('POST   /jobs', $output);
    $this->assertStringContainsString('Closure', $output);
  }
}

class RoutesShowController {
  public function index(): void {}
}
