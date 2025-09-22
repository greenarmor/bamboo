<?php

namespace Tests\Core;

use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Provider\AppProvider;
use Bamboo\Web\Kernel;
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
  public function __construct(private array $items) { parent::__construct(''); }

  public function all(): array { return $this->items; }

  public function setMiddleware(array $middleware): void { $this->items['middleware'] = $middleware; }

  public function setRouteCache(?string $path): void {
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
    $app->get('router')->get('/pipeline', [
      'middleware' => ['beta-group', 'delta'],
      'handler' => function(Request $request) use (&$captured) {
        $captured['alpha'] = $request->getAttribute('alpha');
        $captured['beta'] = $request->getAttribute('beta');
        $captured['gamma'] = $request->getAttribute('gamma');
        $captured['delta'] = $request->getAttribute('delta');
        return new Response(200, [], 'ok');
      },
    ]);

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

    $app->handle(new ServerRequest('GET', '/pipeline'));
    $this->assertSame($cached, $ref->getValue($kernel), 'Route middleware cache should be reused across requests.');
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
}
