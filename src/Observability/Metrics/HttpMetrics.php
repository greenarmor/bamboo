<?php

declare(strict_types=1);

namespace Bamboo\Observability\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;

class HttpMetrics
{
    private Counter $requestsTotal;
    private Counter $requestsFailed;
    private Counter $timeouts;
    private Gauge $inFlight;
    private Histogram $duration;

    /**
     * @param array{namespace?: string, histogram_buckets?: array<string, array<int, float>>} $config
     */
    public function __construct(private CollectorRegistry $registry, private array $config)
    {
        $namespace = $config['namespace'] ?? 'bamboo';
        $buckets = $this->resolveBuckets('bamboo_http_request_duration_seconds');

        $this->requestsTotal = $registry->getOrRegisterCounter(
            $namespace,
            'http_requests_total',
            'Total number of HTTP responses produced by the Bamboo HTTP kernel.',
            ['method', 'route', 'status']
        );

        $this->requestsFailed = $registry->getOrRegisterCounter(
            $namespace,
            'http_requests_failed_total',
            'Total number of HTTP requests that resulted in an unhandled exception.',
            ['method', 'route']
        );

        $this->timeouts = $registry->getOrRegisterCounter(
            $namespace,
            'http_timeouts_total',
            'Total number of HTTP requests aborted by timeout middleware.',
            ['method', 'route']
        );

        $this->inFlight = $registry->getOrRegisterGauge(
            $namespace,
            'http_requests_in_flight',
            'Current number of in-flight HTTP requests being processed by Bamboo.',
            ['method', 'route']
        );

        $this->duration = $registry->getOrRegisterHistogram(
            $namespace,
            'http_request_duration_seconds',
            'HTTP request duration, measured from middleware entry to response emission.',
            ['method', 'route', 'status'],
            $buckets
        );
    }

    /**
     * @return array{started: float, method: string, route: string}
     */
    public function startTimer(string $method, string $route): array
    {
        return [
            'started' => microtime(true),
            'method' => $method,
            'route' => $route,
        ];
    }

    /**
     * @param array{started: float, method: string, route: string} $timer
     */
    public function observeResponse(string $method, string $route, int $statusCode, array $timer): void
    {
        $elapsed = $this->elapsed($timer);
        $status = (string) $statusCode;

        $labels = [$method, $route, $status];
        $this->requestsTotal->inc($labels);
        $this->duration->observe($elapsed, $labels);
    }

    /**
     * @param array{started: float, method: string, route: string} $timer
     */
    public function observeException(string $method, string $route, array $timer): void
    {
        $elapsed = $this->elapsed($timer);
        $status = 'exception';

        $this->requestsFailed->inc([$method, $route]);
        $this->requestsTotal->inc([$method, $route, $status]);
        $this->duration->observe($elapsed, [$method, $route, $status]);
    }

    public function incrementTimeout(string $method, string $route): void
    {
        $this->timeouts->inc([$method, $route]);
    }

    public function incrementInFlight(string $method, string $route): void
    {
        $this->inFlight->inc([$method, $route]);
    }

    public function decrementInFlight(string $method, string $route): void
    {
        $this->inFlight->dec([$method, $route]);
    }

    private function elapsed(array $timer): float
    {
        $started = $timer['started'] ?? microtime(true);
        $delta = microtime(true) - (float) $started;
        return $delta >= 0 ? $delta : 0.0;
    }

    /**
     * @return array<int, float>
     */
    private function resolveBuckets(string $metric): array
    {
        $allBuckets = $this->config['histogram_buckets'] ?? [];
        if (isset($allBuckets[$metric]) && is_array($allBuckets[$metric])) {
            return array_map(static fn($value) => (float) $value, $allBuckets[$metric]);
        }

        if (isset($allBuckets['default']) && is_array($allBuckets['default'])) {
            return array_map(static fn($value) => (float) $value, $allBuckets['default']);
        }

        return [0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0];
    }
}
