<?php

namespace Tests\Core;

use Bamboo\Console\Command\RoutesCache;
use Bamboo\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouterTestApplication;

class RouterCacheTest extends TestCase {
  private array $tempFiles = [];

  protected function tearDown(): void {
    foreach ($this->tempFiles as $file) {
      if (file_exists($file)) @unlink($file);
    }
    parent::tearDown();
  }

  public function testCacheToExportsControllerRoutes(): void {
    $router = new Router();
    $router->get('/users', [DummyController::class, 'index']);
    $router->post('/users', [DummyController::class, 'store']);
    $cacheFile = $this->tempCacheFile();
    $router->cacheTo($cacheFile);
    $this->assertFileExists($cacheFile);
    $this->assertSame([
      'GET' => [
        '/users' => [
          'handler' => [DummyController::class, 'index'],
          'middleware' => [],
          'middleware_groups' => [],
          'signature' => 'GET /users',
        ],
      ],
      'POST'=> [
        '/users' => [
          'handler' => [DummyController::class, 'store'],
          'middleware' => [],
          'middleware_groups' => [],
          'signature' => 'POST /users',
        ],
      ],
    ], require $cacheFile);
  }

  public function testCacheToRejectsClosures(): void {
    $router = new Router();
    $router->get('/closure', fn() => 'ok');
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot cache routes containing closures: GET /closure');
    $router->cacheTo($this->tempCacheFile());
  }

  public function testRoutesCacheCommandReportsClosureHandlers(): void {
    $cacheFile = $this->tempCacheFile();
    $app = new RouterTestApplication([
      ['GET', '/closure', fn() => 'ok'],
    ], ['cache' => ['routes' => $cacheFile]]);
    $command = new RoutesCache($app);
    ob_start();
    $exitCode = $command->handle([]);
    $output = ob_get_clean();
    $this->assertSame(1, $exitCode);
    $this->assertStringContainsString('Routes not cached: Cannot cache routes containing closures: GET /closure', $output);
    $this->assertFileDoesNotExist($cacheFile);
  }

  public function testRoutesCacheCommandCachesRoutes(): void {
    $cacheFile = $this->tempCacheFile();
    $app = new RouterTestApplication([
      ['GET', '/users', [DummyController::class, 'index']],
    ], ['cache' => ['routes' => $cacheFile]]);
    $command = new RoutesCache($app);
    ob_start();
    $exitCode = $command->handle([]);
    $output = ob_get_clean();
    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString("Routes cached -> {$cacheFile}", $output);
    $this->assertFileExists($cacheFile);
    $this->assertSame([
      'GET' => [
        '/users' => [
          'handler' => [DummyController::class, 'index'],
          'middleware' => [],
          'middleware_groups' => [],
          'signature' => 'GET /users',
        ],
      ],
    ], require $cacheFile);
  }

  private function tempCacheFile(): string {
    $dir = sys_get_temp_dir() . '/bamboo-tests';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $file = $dir . '/routes_' . uniqid('', true) . '.php';
    $this->tempFiles[] = $file;
    if (file_exists($file)) @unlink($file);
    return $file;
  }
}

class DummyController {
  public function index(): void {}
  public function store(): void {}
}
