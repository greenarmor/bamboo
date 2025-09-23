<?php

namespace Tests\Roadmap\V0_4;

use Bamboo\Observability\Metrics\HttpMetrics;
use Bamboo\Web\Middleware\TimeoutMiddleware;
use Bamboo\Web\RequestContext;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Tests\Support\ArrayConfig;

class TimeoutMiddlewareTest extends TestCase {
  public function testTimeoutMiddlewareAbortsLongRunningRequests(): void {
    $config = new ArrayConfig([
      'resilience' => [
        'timeouts' => [
          'default' => 0.01,
          'per_route' => [],
        ],
      ],
    ]);

    $registry = new CollectorRegistry(new InMemory());
    $metrics = new HttpMetrics($registry, ['namespace' => 'test']);
    $context = new RequestContext();
    $context->merge(['route' => 'GET /slow']);

    $middleware = new TimeoutMiddleware($config, $context, $metrics);
    $request = new ServerRequest('GET', '/slow');

    $response = $middleware->handle($request, function() {
      usleep(20000); // 20ms
      return new Response(200, ['Content-Type' => 'application/json'], json_encode(['ok' => true], JSON_THROW_ON_ERROR));
    });

    $this->assertSame(504, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $this->assertSame('0.010', $response->getHeaderLine('X-Bamboo-Timeout'));

    $payload = json_decode((string) $response->getBody(), true);
    $this->assertSame('Gateway Timeout', $payload['error']);
    $this->assertSame('GET /slow', $payload['route']);
    $this->assertGreaterThan(0.01, $payload['elapsed']);
  }

  public function testTimeoutMiddlewareRecordsTimeoutMetrics(): void {
    $config = new ArrayConfig([
      'resilience' => [
        'timeouts' => [
          'default' => 0.005,
          'per_route' => [],
        ],
      ],
    ]);

    $registry = new CollectorRegistry(new InMemory());
    $metrics = new HttpMetrics($registry, ['namespace' => 'test']);
    $context = new RequestContext();
    $context->merge(['route' => 'GET /metrics']);

    $middleware = new TimeoutMiddleware($config, $context, $metrics);
    $request = new ServerRequest('GET', '/metrics');

    $middleware->handle($request, function() {
      usleep(10000); // 10ms
      return new Response(200);
    });

    $families = $registry->getMetricFamilySamples();
    $timeoutMetric = null;
    foreach ($families as $family) {
      if ($family->getName() === 'test_http_timeouts_total') {
        $timeoutMetric = $family;
        break;
      }
    }

    $this->assertNotNull($timeoutMetric);
    $samples = $timeoutMetric->getSamples();
    $this->assertCount(1, $samples);
    $this->assertSame(1.0, (float) $samples[0]->getValue());
    $this->assertSame(['GET', 'GET /metrics'], array_slice($samples[0]->getLabelValues(), 0, 2));
  }

  public function testTimeoutMiddlewareRespectsPerRouteOverride(): void {
    $config = new ArrayConfig([
      'resilience' => [
        'timeouts' => [
          'default' => 0.01,
          'per_route' => [
            'GET /slow' => ['timeout' => 0.05],
          ],
        ],
      ],
    ]);

    $registry = new CollectorRegistry(new InMemory());
    $metrics = new HttpMetrics($registry, ['namespace' => 'test']);
    $context = new RequestContext();
    $context->merge(['route' => 'GET /slow']);

    $middleware = new TimeoutMiddleware($config, $context, $metrics);
    $request = new ServerRequest('GET', '/slow');

    $response = $middleware->handle($request, function() {
      usleep(20000); // 20ms
      return new Response(200, ['Content-Type' => 'application/json'], json_encode(['ok' => true], JSON_THROW_ON_ERROR));
    });

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $elapsedHeader = $response->getHeaderLine('X-Bamboo-Elapsed');
    $this->assertNotSame('', $elapsedHeader);
    $this->assertGreaterThanOrEqual(0.015, (float) $elapsedHeader);
    $this->assertLessThan(0.05, (float) $elapsedHeader);
  }
}
