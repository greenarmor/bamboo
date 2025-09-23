<?php

declare(strict_types=1);

namespace Bamboo\Observability\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;

final class CircuitBreakerMetrics
{
    private Counter $failures;
    private Counter $opens;
    private Gauge $state;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private CollectorRegistry $registry, array $config)
    {
        $namespace = is_string($config['namespace'] ?? null) ? $config['namespace'] : 'bamboo';

        $this->failures = $registry->getOrRegisterCounter(
            $namespace,
            'http_circuit_breaker_failures_total',
            'Total number of HTTP requests recorded as circuit breaker failures.',
            ['method', 'route']
        );

        $this->opens = $registry->getOrRegisterCounter(
            $namespace,
            'http_circuit_breaker_open_total',
            'Total number of times the HTTP circuit breaker transitioned to the open state.',
            ['method', 'route']
        );

        $this->state = $registry->getOrRegisterGauge(
            $namespace,
            'http_circuit_breaker_state',
            'Current circuit breaker state for each route (0=closed,1=half-open,2=open).',
            ['method', 'route']
        );
    }

    public function recordFailure(string $method, string $route): void
    {
        $this->failures->inc([$method, $route]);
    }

    public function recordOpen(string $method, string $route): void
    {
        $this->opens->inc([$method, $route]);
        $this->state->set(2, [$method, $route]);
    }

    public function recordHalfOpen(string $method, string $route): void
    {
        $this->state->set(1, [$method, $route]);
    }

    public function recordClosed(string $method, string $route): void
    {
        $this->state->set(0, [$method, $route]);
    }

    public function markOpenState(string $method, string $route): void
    {
        $this->state->set(2, [$method, $route]);
    }
}
