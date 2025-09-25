<?php

namespace Tests\Http;

use Bamboo\Provider\AppProvider;
use Bamboo\Provider\MetricsProvider;
use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Web\View\Engine\TemplateEngineInterface;
use Bamboo\Web\View\Engine\TemplateEngineManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Prometheus\RenderTextFormat;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\PredisFakeServer;
use Tests\Stubs\PredisMemoryConnection;
use ReflectionClass;

class ApplicationRoutesTest extends TestCase {
  protected function setUp(): void {
    parent::setUp();
    PredisFakeServer::reset();
    $_ENV['LOG_FILE'] = 'php://temp';
    $_ENV['REDIS_URL'] = 'memory://local';
  }

  private function createApp(): Application {
    $config = new Config(dirname(__DIR__, 2) . '/etc');
    $app = new Application($config);
    $app->register(new AppProvider());
    $app->register(new MetricsProvider());
    $app->register(new \Bamboo\Provider\ResilienceProvider());
    $app->singleton('redis.client.factory', function() use ($app) {
      return function(array $overrides = []) use ($app) {
        $config = array_replace($app->config('redis') ?? [], $overrides);
        $url = $config['url'] ?? 'memory://local';
        $options = $config['options'] ?? [];
        $options['connections']['memory'] = PredisMemoryConnection::factory();
        return new \Predis\Client($url, $options);
      };
    });
    return $app;
  }

  public function testHomeRouteBootstrapsLandingExperienceFromApi(): void {
    $app = $this->createApp();
    $response = $app->handle(new ServerRequest('GET', '/'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));

    $body = (string) $response->getBody();
    $this->assertStringContainsString('id="landing-root"', $body);
    $this->assertStringContainsString("fetch('/api/landing'", $body);

    $loadingMessage = sprintf('Loading %s experience…', $app->config('app.name', 'Bamboo'));
    $this->assertStringContainsString($loadingMessage, html_entity_decode($body, ENT_QUOTES));
    $this->assertStringContainsString('Enable JavaScript to view the Bamboo landing experience.', $body);
  }

  public function testLandingPageApiProvidesDynamicMarkupPayload(): void {
    $app = $this->createApp();
    $response = $app->handle(new ServerRequest('GET', '/api/landing'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

    $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

    $this->assertIsArray($payload);
    $this->assertArrayHasKey('html', $payload);
    $this->assertArrayHasKey('template', $payload);
    $this->assertArrayHasKey('meta', $payload);

    $html = $payload['html'];
    $this->assertIsString($html);
    $this->assertStringContainsString('class="bamboo-hero"', $html);
    $this->assertStringContainsString('Bamboo makes high-performance PHP approachable.', $html);
    $this->assertStringContainsString('Powered by OpenSwoole', $html);
    $this->assertStringContainsString('Environment ready', $html);
    $this->assertStringContainsString('php bin/bamboo http.serve', $html);

    $expectedSwoole = $this->expectedSwooleVersion();
    $this->assertStringContainsString($expectedSwoole, $html);

    $template = $payload['template'];
    $this->assertIsArray($template);
    $this->assertSame(1, $template['version'] ?? null);
    $this->assertSame('page', $template['component'] ?? null);
    $this->assertArrayHasKey('children', $template);
    $this->assertIsArray($template['children']);
    $this->assertNotEmpty($template['children']);
    $this->assertSame('hero', $template['children'][0]['component'] ?? null);

    $meta = $payload['meta'];
    $this->assertIsArray($meta);
    $this->assertSame(
      sprintf('%s | Modern PHP Microframework', $app->config('app.name', 'Bamboo')),
      $meta['title'] ?? null
    );
    $this->assertSame('Bamboo makes high-performance PHP approachable.', $meta['description'] ?? null);
    $this->assertArrayHasKey('generated_at', $meta);
  }

  public function testLandingPageRespectsCustomTemplateEngine(): void {
    $app = $this->createApp();
    $config = $app->get(Config::class);
    $this->overrideViewConfig($config, function(array $items) {
      $items['view']['default'] = 'custom';
      $items['view']['engines']['custom'] = ['driver' => 'custom'];
      return $items;
    });

    /** @var TemplateEngineManager $manager */
    $manager = $app->get(TemplateEngineManager::class);
    $manager->extend('custom', function(Application $app, array $engineConfig) {
      return new class implements TemplateEngineInterface {
        public function render(array $template, array $context = []): string {
          return 'custom:' . ($template['component'] ?? 'unknown');
        }
      };
    });

    $response = $app->handle(new ServerRequest('GET', '/api/landing'));

    $this->assertSame(200, $response->getStatusCode());

    $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    $this->assertIsArray($payload);
    $this->assertSame('custom:page', $payload['html'] ?? null);
  }

  public function testHelloRouteGreetsName(): void {
    $app = $this->createApp();
    $response = $app->handle(new ServerRequest('GET', '/hello/Bamboo'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('Hello, Bamboo!' . "\n", (string) $response->getBody());
    $this->assertSame('text/plain', $response->getHeaderLine('Content-Type'));
  }

  public function testEchoRouteReturnsPostedJson(): void {
    $app = $this->createApp();
    $psr17 = new Psr17Factory();
    $body = $psr17->createStream(json_encode(['ok' => true], JSON_THROW_ON_ERROR));
    $request = (new ServerRequest('POST', '/api/echo'))->withBody($body);
    $response = $app->handle($request);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $this->assertSame(['ok' => true], json_decode((string) $response->getBody(), true));
  }

  public function testJobRouteEnqueuesPayload(): void {
    $app = $this->createApp();
    $psr17 = new Psr17Factory();
    $payload = json_encode(['task' => 'demo'], JSON_THROW_ON_ERROR);
    $request = (new ServerRequest('POST', '/api/jobs'))->withBody($psr17->createStream($payload));

    $response = $app->handle($request);

    $this->assertSame(202, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $this->assertSame(['queued' => true], json_decode((string) $response->getBody(), true));
    $queue = PredisFakeServer::dumpQueue('jobs');
    $this->assertCount(1, $queue);
    $this->assertSame(['task' => 'demo'], json_decode($queue[0], true));
  }

  public function testClosureRouteWithSingleParameterReceivesRequest(): void {
    $app = $this->createApp();
    $router = $app->get('router');
    $capturedRequest = null;

    $router->get('/test/single', function(ServerRequest $request) use (&$capturedRequest) {
      $capturedRequest = $request;
      return new Response(200, ['Content-Type' => 'text/plain'], 'ok');
    });

    $request = new ServerRequest('GET', '/test/single');
    $response = $app->handle($request);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertInstanceOf(ServerRequest::class, $capturedRequest);
    $this->assertSame('GET', $capturedRequest->getMethod());
    $this->assertSame('/test/single', $capturedRequest->getUri()->getPath());
  }

  public function testClosureRouteWithTwoParametersReceivesRequestAndVars(): void {
    $app = $this->createApp();
    $router = $app->get('router');
    $capturedRequest = null;
    $capturedVars = null;

    $router->get('/test/two/{value}', function(ServerRequest $request, array $vars) use (&$capturedRequest, &$capturedVars) {
      $capturedRequest = $request;
      $capturedVars = $vars;
      return new Response(200, ['Content-Type' => 'application/json'], json_encode(['value' => $vars['value'] ?? null], JSON_THROW_ON_ERROR));
    });

    $request = new ServerRequest('GET', '/test/two/demo');
    $response = $app->handle($request);

      $this->assertSame(200, $response->getStatusCode());
      $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
      $this->assertSame(['value' => 'demo'], json_decode((string) $response->getBody(), true));
      $this->assertInstanceOf(ServerRequest::class, $capturedRequest);
      $this->assertSame($request->getUri()->getPath(), $capturedRequest->getUri()->getPath());
      $this->assertSame(['value' => 'demo'], $capturedVars);
    }

  public function testMetricsRouteRendersPrometheusTextFormat(): void {
    $app = $this->createApp();
    $response = $app->handle(new ServerRequest('GET', '/metrics'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(RenderTextFormat::MIME_TYPE, $response->getHeaderLine('Content-Type'));

    $body = (string) $response->getBody();
    $this->assertNotSame('', $body);
    $this->assertStringContainsString('# HELP bamboo_http_requests_in_flight', $body);
    $this->assertStringContainsString('bamboo_http_requests_in_flight', $body);
  }

  private function expectedSwooleVersion(): string {
    if (defined('SWOOLE_VERSION')) {
      return SWOOLE_VERSION;
    }

    foreach (['openswoole', 'swoole'] as $extension) {
      if (extension_loaded($extension)) {
        $version = phpversion($extension);
        if (is_string($version) && $version !== '') {
          return $version;
        }
      }
    }

    return 'not installed';
  }

  /**
   * @param callable(array<string,mixed>):array<string,mixed> $mutator
   */
  private function overrideViewConfig(Config $config, callable $mutator): void {
    $ref = new ReflectionClass($config);
    $property = $ref->getProperty('items');
    $property->setAccessible(true);
    /** @var array<string,mixed> $items */
    $items = $property->getValue($config);
    $items = $mutator($items);
    $property->setValue($config, $items);
  }
}
