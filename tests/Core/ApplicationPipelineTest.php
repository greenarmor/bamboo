<?php

namespace Tests\Core;

use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Core\RouteDefinition;
use Bamboo\Provider\AppProvider;
use Bamboo\Provider\MetricsProvider;
use Bamboo\Provider\ResilienceProvider;
use Bamboo\Web\Kernel;
use Bamboo\Web\RequestContextScope;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class PipelineRecorder {
  /** @var list<string> */
  public static array $events = [];

  public static function reset(): void { self::$events = []; }
}

class AlphaMiddleware {
  public function handle(Request $request, \Closure $next): ResponseInterface {
    PipelineRecorder::$events[] = 'alpha:before';
    $response = $next($request->withAttribute('alpha', true));
    PipelineRecorder::$events[] = 'alpha:after';
    return $response->withHeader('X-Alpha', '1');
  }
}

class BetaMiddleware {
  public function handle(Request $request, \Closure $next): ResponseInterface {
    PipelineRecorder::$events[] = 'beta:before';
    $response = $next($request->withAttribute('beta', true));
    PipelineRecorder::$events[] = 'beta:after';
    return $response->withHeader('X-Beta', '1');
  }
}

class GammaMiddleware {
  public function handle(Request $request, \Closure $next): ResponseInterface {
    PipelineRecorder::$events[] = 'gamma:before';
    $response = $next($request->withAttribute('gamma', true));
    PipelineRecorder::$events[] = 'gamma:after';
    return $response->withHeader('X-Gamma', '1');
  }
}

class DeltaMiddleware {
  public function handle(Request $request, \Closure $next): ResponseInterface {
    PipelineRecorder::$events[] = 'delta:before';
    $response = $next($request->withAttribute('delta', true));
    PipelineRecorder::$events[] = 'delta:after';
    return $response->withHeader('X-Delta', '1');
  }
}

class TerminableAlphaMiddleware {
  public function handle(Request $request, \Closure $next): ResponseInterface {
    PipelineRecorder::$events[] = 'terminable-alpha:before';
    $request = $request->withAttribute('terminable-alpha', 'set');
    $response = $next($request);
    PipelineRecorder::$events[] = 'terminable-alpha:after';
    return $response->withHeader('X-Term-Alpha', 'A');
  }

  public function terminate(Request $request, ResponseInterface $response): void {
    PipelineRecorder::$events[] = sprintf(
      'terminable-alpha:terminate request=%s response=%s',
      $request->getAttribute('terminable-alpha'),
      $response->getHeaderLine('X-Term-Beta')
    );
  }
}

class TerminableBetaMiddleware {
  public function handle(Request $request, \Closure $next): ResponseInterface {
    PipelineRecorder::$events[] = 'terminable-beta:before';
    $request = $request->withAttribute('terminable-beta', 'set');
    $response = $next($request);
    PipelineRecorder::$events[] = 'terminable-beta:after';
    return $response->withHeader('X-Term-Beta', 'B');
  }

  public function terminate(Request $request, ResponseInterface $response): void {
    PipelineRecorder::$events[] = sprintf(
      'terminable-beta:terminate request-alpha=%s request-beta=%s response=%s',
      $request->getAttribute('terminable-alpha'),
      $request->getAttribute('terminable-beta'),
      $response->getHeaderLine('X-Term-Alpha')
    );
  }
}

class ArrayConfig extends Config {
  /**
   * @param array<string, mixed> $configuration
   */
  public function __construct(private array $configuration) { parent::__construct(''); }

  /**
   * @return array<string, mixed>
   */
  protected function loadConfiguration(): array { return $this->configuration; }

  /**
   * @param array<string, mixed> $middleware
   */
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

  /**
   * @param array<string, mixed> $middleware
   */
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
    $app->register(new MetricsProvider());
    $app->register(new ResilienceProvider());
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

    $scope = $app->get(RequestContextScope::class);
    $contextAfterFirst = $scope->get();
    $this->assertInstanceOf(\Bamboo\Web\RequestContext::class, $contextAfterFirst);
    $this->assertSame('GET /missing-one', $contextAfterFirst->get('route'));

    $firstCache = $ref->getValue($kernel);
    $this->assertSame(['__global__'], array_keys($firstCache));

    $responseTwo = $app->handle(new ServerRequest('GET', '/missing-two'));
    $this->assertSame(404, $responseTwo->getStatusCode());

    $contextAfterSecond = $scope->get();
    $this->assertInstanceOf(\Bamboo\Web\RequestContext::class, $contextAfterSecond);
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

  public function testTerminableMiddlewareRunsAfterResponseIsProduced(): void {
    $middleware = [
      'global' => ['terminable-alpha'],
      'groups' => [],
      'aliases' => [
        'terminable-alpha' => TerminableAlphaMiddleware::class,
        'terminable-beta' => TerminableBetaMiddleware::class,
      ],
    ];

    $config = $this->baseConfig($middleware);
    $app = $this->createApp($config);

    $app->get('router')->get('/terminate', RouteDefinition::forHandler(
      function(Request $request) {
        PipelineRecorder::$events[] = sprintf(
          'handler:alpha=%s beta=%s',
          $request->getAttribute('terminable-alpha'),
          $request->getAttribute('terminable-beta')
        );
        return (new Response(200, [], 'ok'))->withHeader('X-Final', '1');
      },
      middleware: ['terminable-beta']
    ));

    $response = $app->handle(new ServerRequest('GET', '/terminate'));

    $this->assertSame('A', $response->getHeaderLine('X-Term-Alpha'));
    $this->assertSame('B', $response->getHeaderLine('X-Term-Beta'));
    $this->assertSame('1', $response->getHeaderLine('X-Final'));

    $this->assertSame([
      'terminable-alpha:before',
      'terminable-beta:before',
      'handler:alpha=set beta=set',
      'terminable-beta:after',
      'terminable-alpha:after',
      'terminable-beta:terminate request-alpha=set request-beta=set response=A',
      'terminable-alpha:terminate request=set response=B',
    ], PipelineRecorder::$events);
  }
}
