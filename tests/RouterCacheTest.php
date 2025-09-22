<?php

namespace Tests;

use Bamboo\Console\Command\RoutesCache;
use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Core\Router;
use PHPUnit\Framework\TestCase;

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
      'GET' => ['/users' => [DummyController::class, 'index']],
      'POST'=> ['/users' => [DummyController::class, 'store']],
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
    $app = new TestApplication([
      ['GET', '/closure', fn() => 'ok'],
    ], $cacheFile);
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
    $app = new TestApplication([
      ['GET', '/users', [DummyController::class, 'index']],
    ], $cacheFile);
    $command = new RoutesCache($app);
    ob_start();
    $exitCode = $command->handle([]);
    $output = ob_get_clean();
    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString("Routes cached -> {$cacheFile}", $output);
    $this->assertFileExists($cacheFile);
    $this->assertSame([
      'GET' => ['/users' => [DummyController::class, 'index']],
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

class ArrayConfig extends Config {
  public function __construct(private array $items) {}
  public function all(): array { return $this->items; }
  public function get(?string $key = null, $default = null) {
    if ($key === null) return $this->items;
    $value = $this->items;
    foreach (explode('.', $key) as $segment) {
      if (!is_array($value) || !array_key_exists($segment, $value)) return $default;
      $value = $value[$segment];
    }
    return $value;
  }
}

class TestApplication extends Application {
  private Router $routerInstance;
  public function __construct(private array $routeDefinitions, string $cacheFile) {
    $this->routerInstance = new Router();
    $config = new ArrayConfig([
      'app' => [],
      'server' => [],
      'cache' => ['routes' => $cacheFile],
      'redis' => [],
      'database' => ['connections' => [], 'default' => null],
      'ws' => [],
      'http' => [],
    ]);
    parent::__construct($config);
  }
  protected function bootRoutes(): void {
    $this->singleton('router', fn() => $this->routerInstance);
    $router = $this->get('router');
    foreach ($this->routeDefinitions as $definition) {
      [$method, $path, $handler] = $definition;
      $router->{strtolower($method)}($path, $handler);
    }
  }
}
