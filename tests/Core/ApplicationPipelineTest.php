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

class ApplicationPipelineTest extends TestCase {
  protected function setUp(): void {
    parent::setUp();
    PipelineRecorder::reset();
  }

  private function createApp(): Application {
    $config = new Config(dirname(__DIR__, 2) . '/etc');
    $app = new Application($config);
    $app->register(new AppProvider());
    return $app;
  }

  public function testMiddlewarePipelineRunsInConfiguredOrder(): void {
    $app = $this->createApp();
    $app->bind(Kernel::class, fn() => new class extends Kernel {
      public array $middleware = [
        AlphaMiddleware::class,
        BetaMiddleware::class,
        \Bamboo\Web\Middleware\SignatureHeader::class,
      ];
    });

    $captured = [];
    $app->get('router')->get('/pipeline', function(Request $request) use (&$captured) {
      $captured['alpha'] = $request->getAttribute('alpha');
      $captured['beta'] = $request->getAttribute('beta');
      return new Response(200, [], 'ok');
    });

    $response = $app->handle(new ServerRequest('GET', '/pipeline'));

    $this->assertSame([
      'alpha:before',
      'beta:before',
      'beta:after',
      'alpha:after',
    ], PipelineRecorder::$events);
    $this->assertSame('1', $response->getHeaderLine('X-Alpha'));
    $this->assertSame('1', $response->getHeaderLine('X-Beta'));
    $this->assertSame('fast', $response->getHeaderLine('X-Bamboo'));
    $this->assertTrue($captured['alpha']);
    $this->assertTrue($captured['beta']);
  }
}
