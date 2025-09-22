<?php

namespace Tests\Core;

use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Core\RouteDefinition;
use Bamboo\Provider\AppProvider;
use Bamboo\Web\Kernel;
use Bamboo\Web\RequestContext;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;

class PipelineRecorder {
  public static array $events = [];

  public static function reset(): void { self::$events = []; }
}

class AlphaMiddleware {
  public function handle(Request $request, \Closure $next) {
    PipelineRecorder::$events[] = 'alpha:before';
    $response = $next($request->withAttribute('alpha', true));
    PipelineRecorder::$events[] = 'alpha:after';
    return $response->withHeader('X-Alpha', '1');
  }
}

class BetaMiddleware {
  public function handle(Request $request, \Closure $next) {
    PipelineRecorder::$events[] = 'beta:before';
    $response = $next($request->withAttribute('beta', true));
    PipelineRecorder::$events[] = 'beta:after';
    return $response->withHeader('X-Beta', '1');
  }
}

class GammaMiddleware {
  public function handle(Request $request, \Closure $next) {
    PipelineRecorder::$events[] = 'gamma:before';
    $response = $next($request->withAttribute('gamma', true));
    PipelineRecorder::$events[] = 'gamma:after';
    return $response->withHeader('X-Gamma', '1');
  }
}

class DeltaMiddleware {
  public function handle(Request $request, \Closure $next) {
    PipelineRecorder::$events[] = 'delta:before';
    $response = $next($request->withAttribute('delta', true));
    PipelineRecorder::$events[] = 'delta:after';
    return $response->withHeader('X-Delta', '1');
  }
}

class ArrayConfig extends Config {
  public function __construct(private array $configuration) { parent::__construct(''); }

  protected function loadConfiguration(): array { return $this->configuration; }

  public function setMiddleware(array $middleware): void {
    $this->configuration['middleware'] = $middleware;
    $this->items['middleware'] = $middleware;
  }

  public function setRouteCache(?string $path): void {
    $this->configuration['cache']['routes'] = $path;
    $this->items['cache']['routes'] = $path;
  }
}

class ApplicationPipelineTest extends TestCase {
  protected function setUp(): void {
    parent::setUp();
    PipelineRecorder::reset();
  }

  private function baseConfig(array $middleware, ?string $routeCachePath = null): ArrayConfig {
    $dir = dirname(__DIR__, 2) . '/etc';

    $items = [
      'app' => require $dir . '/app.php',
      'server' => require $dir . '/server.php',
      'cache' => require $dir . '/cache.php',
      'redis' => require $dir . '/redis.php',
      'database' => require $dir . '/database.php',
      'ws' => require $dir . '/ws.php',
      'http' => require $dir . '/http.php',
      'middleware' => $middleware,
    ];

    if ($routeCachePath !== null) {
      $items['cache']['routes'] = $routeCachePath;
    }

    return new ArrayConfig($items);
  }

  private function createApp(Config $config): Application {
    $app = new Application($config);
    $app->register(new AppProvider());
    return $app;
  }

  public function testMiddlewarePipelineResolvesConfiguredOrder(): void {
    $middleware = [
      'global' => [
        \Bamboo\Web\Middleware\RequestId::class,
        'alpha',
        \Bamboo\Web\Middleware\SignatureHeader::class,
      ],
      'groups' => [
        'beta-group' => [
          BetaMiddleware::class,
          'gamma-group',
        ],
        'gamma-group' => [
          'gamma',
        ],
      ],
      'aliases' => [
        'alpha' => AlphaMiddleware::class,
        'gamma' => GammaMiddleware::class,
        'delta' => DeltaMiddleware::class,
      ],
    ];

    $config = $this->baseConfig($middleware);
    $app = $this->createApp($config);

    $captured = [];
    $app->get('router')->get('/pipeline', RouteDefinition::forHandler(
      function(Request $request) use (&$captured) {
        $captured['alpha'] = $request->getAttribute('alpha');
        $captured['beta'] = $request->getAttribute('beta');
        $captured['gamma'] = $request->getAttribute('gamma');
        $captured['delta'] = $request->getAttribute('delta');
        return new Response(200, [], 'ok');
      },
      middleware: ['delta'],
      middlewareGroups: ['beta-group'],
    ));

    $routerResponse = $app->handle(new ServerRequest('GET', '/pipeline'));

    $this->assertSame([
      'alpha:before',
      'beta:before',
      'gamma:before',
      'delta:before',
      'delta:after',
      'gamma:after',
      'beta:after',
      'alpha:after',
    ], PipelineRecorder::$events);

    $this->assertSame('1', $routerResponse->getHeaderLine('X-Alpha'));
    $this->assertSame('1', $routerResponse->getHeaderLine('X-Beta'));
    $this->assertSame('1', $routerResponse->getHeaderLine('X-Gamma'));
    $this->assertSame('1', $routerResponse->getHeaderLine('X-Delta'));
    $this->assertSame('fast', $routerResponse->getHeaderLine('X-Bamboo'));

    $this->assertTrue($captured['alpha']);
    $this->assertTrue($captured['beta']);
    $this->assertTrue($captured['gamma']);
    $this->assertTrue($captured['delta']);

    $kernel = $app->get(Kernel::class);
    $ref = new \ReflectionProperty($kernel, 'resolved');
    $ref->setAccessible(true);
    $cached = $ref->getValue($kernel);
    $this->assertArrayHasKey('GET /pipeline', $cached);
    $this->assertSame([
      \Bamboo\Web\Middleware\RequestId::class,
      AlphaMiddleware::class,
      \Bamboo\Web\Middleware\SignatureHeader::class,
      BetaMiddleware::class,
      GammaMiddleware::class,
      DeltaMiddleware::class,
    ], $cached['GET /pipeline']);

    $app->handle(new ServerRequest('GET', '/pipeline'));
    $this->assertSame($cached, $ref->getValue($kernel), 'Route middleware cache should be reused across requests.');
  }

  public function testUnmatchedRoutesShareKernelCacheEntry(): void {
    $middleware = [
      'global' => ['alpha'],
      'groups' => [],
      'aliases' => [
        'alpha' => AlphaMiddleware::class,
      ],
    ];

    $config = $this->baseConfig($middleware);
    $app = $this->createApp($config);

    $kernel = $app->get(Kernel::class);
    $ref = new \ReflectionProperty($kernel, 'resolved');
    $ref->setAccessible(true);

    $firstResponse = $app->handle(new ServerRequest('GET', '/missing-one'));
    $this->assertSame(404, $firstResponse->getStatusCode());

    $cached = $ref->getValue($kernel);
    $this->assertArrayHasKey('__global__', $cached);
    $this->assertCount(1, $cached);

    $secondResponse = $app->handle(new ServerRequest('GET', '/missing-two'));
    $this->assertSame(404, $secondResponse->getStatusCode());

    $this->assertSame($cached, $ref->getValue($kernel));
    $this->assertSame([
      'alpha:before',
      'alpha:after',
      'alpha:before',
      'alpha:after',
    ], PipelineRecorder::$events);
  }

  public function testKernelCacheInvalidatesWhenConfigurationChanges(): void {
    $config = $this->baseConfig([
      'global' => ['alpha'],
      'groups' => [],
      'aliases' => [
        'alpha' => AlphaMiddleware::class,
        'beta' => BetaMiddleware::class,
      ],
    ]);

    $kernel = new Kernel($config);

    $first = $kernel->forRoute('GET /cache', []);
    $this->assertSame([AlphaMiddleware::class], $first);

    $config->setMiddleware([
      'global' => ['beta'],
      'groups' => [],
      'aliases' => [
        'alpha' => AlphaMiddleware::class,
        'beta' => BetaMiddleware::class,
      ],
    ]);

    $second = $kernel->forRoute('GET /cache', []);
    $this->assertSame([BetaMiddleware::class], $second);
  }

  public function testKernelCacheInvalidatesWhenRouteCacheTimestampChanges(): void {
    $temp = tempnam(sys_get_temp_dir(), 'bamboo_routes_');
    touch($temp);

    $config = $this->baseConfig([
      'global' => [],
      'groups' => [],
      'aliases' => [
        'alpha' => AlphaMiddleware::class,
        'beta' => BetaMiddleware::class,
      ],
    ], $temp);

    $kernel = new Kernel($config);

    $first = $kernel->forRoute('GET /cache', ['alpha']);
    $this->assertSame([AlphaMiddleware::class], $first);

    clearstatcache();
    touch($temp, time() + 2);
    clearstatcache();

    $second = $kernel->forRoute('GET /cache', ['beta']);
    $this->assertSame([BetaMiddleware::class], $second);

    @unlink($temp);
  }

  public function testKernelCachesSingleEntryForUnmatchedRoutes(): void {
    $config = $this->baseConfig([
      'global' => ['alpha'],
      'groups' => [],
      'aliases' => [
        'alpha' => AlphaMiddleware::class,
      ],
    ]);

    $app = $this->createApp($config);
    $kernel = $app->get(Kernel::class);
    $ref = new \ReflectionProperty($kernel, 'resolved');
    $ref->setAccessible(true);

    $this->assertSame([], $ref->getValue($kernel));

    $responseOne = $app->handle(new ServerRequest('GET', '/missing-one'));
    $this->assertSame(404, $responseOne->getStatusCode());

    $contextAfterFirst = $app->get(RequestContext::class);
    $this->assertSame('GET /missing-one', $contextAfterFirst->get('route'));

    $firstCache = $ref->getValue($kernel);
    $this->assertSame(['__global__'], array_keys($firstCache));

    $responseTwo = $app->handle(new ServerRequest('GET', '/missing-two'));
    $this->assertSame(404, $responseTwo->getStatusCode());

    $contextAfterSecond = $app->get(RequestContext::class);
    $this->assertSame('GET /missing-two', $contextAfterSecond->get('route'));

    $secondCache = $ref->getValue($kernel);
    $this->assertSame(['__global__'], array_keys($secondCache));
    $this->assertSame($firstCache, $secondCache);

    $this->assertSame([
      'alpha:before',
      'alpha:after',
      'alpha:before',
      'alpha:after',
    ], PipelineRecorder::$events);
  }
}
