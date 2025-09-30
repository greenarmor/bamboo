<?php

namespace Tests\Roadmap\V0_4;

use Bamboo\Observability\Metrics\CircuitBreakerMetrics;
use Bamboo\Web\Middleware\CircuitBreakerMiddleware;
use Bamboo\Web\RequestContextScope;
use Bamboo\Web\Resilience\CircuitBreakerRegistry;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use RuntimeException;
use Tests\Support\ArrayConfig;

class CircuitBreakerMiddlewareTest extends TestCase {
  public function testCircuitBreakerOpensAfterFailureThreshold(): void {
    $config = new ArrayConfig([
      'resilience' => [
        'circuit_breaker' => [
          'enabled' => true,
          'failure_threshold' => 2,
          'cooldown_seconds' => 0.05,
          'success_threshold' => 1,
          'per_route' => [],
        ],
      ],
    ]);

    $registry = new CircuitBreakerRegistry();
    $metrics = new CircuitBreakerMetrics(new CollectorRegistry(new InMemory()), ['namespace' => 'test']);
    $scope = new RequestContextScope();
    $scope->getOrCreate()->merge(['route' => 'GET /unstable']);

    $now = 0.0;
    $clock = function() use (&$now): float { return $now; };

    $middleware = new CircuitBreakerMiddleware($config, $scope, $registry, $metrics, $clock);
    $request = new ServerRequest('GET', '/unstable');

    try {
      $middleware->handle($request, function() {
        throw new RuntimeException('boom');
      });
    } catch (RuntimeException) {
      // ignored
    }

    try {
      $middleware->handle($request, function() {
        throw new RuntimeException('boom');
      });
    } catch (RuntimeException) {
      // ignored
    }

    $response = $middleware->handle($request, function() {
      throw new RuntimeException('should not be executed');
    });

    $this->assertSame(503, $response->getStatusCode());
    $this->assertSame('Service Unavailable', json_decode((string) $response->getBody(), true)['error']);

    $now = 0.2; // advance beyond cooldown
    $response = $middleware->handle($request, function() {
      return new Response(200, ['Content-Type' => 'application/json'], json_encode(['ok' => true], JSON_THROW_ON_ERROR));
    });

    $this->assertSame(200, $response->getStatusCode());
  }

  public function testCircuitBreakerPublishesStateMetrics(): void {
    $config = new ArrayConfig([
      'resilience' => [
        'circuit_breaker' => [
          'enabled' => true,
          'failure_threshold' => 1,
          'cooldown_seconds' => 0.1,
          'success_threshold' => 1,
          'per_route' => [],
        ],
      ],
    ]);

    $collector = new CollectorRegistry(new InMemory());
    $metrics = new CircuitBreakerMetrics($collector, ['namespace' => 'test']);
    $registry = new CircuitBreakerRegistry();
    $scope = new RequestContextScope();
    $scope->getOrCreate()->merge(['route' => 'GET /metrics']);

    $now = 0.0;
    $clock = function() use (&$now): float { return $now; };

    $middleware = new CircuitBreakerMiddleware($config, $scope, $registry, $metrics, $clock);
    $request = new ServerRequest('GET', '/metrics');

    try {
      $middleware->handle($request, function() {
        throw new RuntimeException('boom');
      });
    } catch (RuntimeException) {
      // ignored
    }

    $families = $collector->getMetricFamilySamples();
    $stateGauge = null;
    $openCounter = null;
    foreach ($families as $family) {
      if ($family->getName() === 'test_http_circuit_breaker_state') {
        $stateGauge = $family;
      }
      if ($family->getName() === 'test_http_circuit_breaker_open_total') {
        $openCounter = $family;
      }
    }

    $this->assertNotNull($stateGauge);
    $this->assertNotNull($openCounter);

    $this->assertSame(2.0, (float) $stateGauge->getSamples()[0]->getValue());
    $this->assertSame(1.0, (float) $openCounter->getSamples()[0]->getValue());

    $now = 0.2;
    $middleware->handle($request, function() {
      return new Response(200);
    });

    $families = $collector->getMetricFamilySamples();
    foreach ($families as $family) {
      if ($family->getName() === 'test_http_circuit_breaker_state') {
        $this->assertSame(0.0, (float) $family->getSamples()[0]->getValue());
      }
    }
  }
}
