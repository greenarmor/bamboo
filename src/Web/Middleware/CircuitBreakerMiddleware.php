<?php

declare(strict_types=1);

namespace Bamboo\Web\Middleware;

use Bamboo\Core\Config;
use Bamboo\Observability\Metrics\CircuitBreakerMetrics;
use Bamboo\Web\RequestContextScope;
use Bamboo\Web\Resilience\CircuitBreakerRegistry;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CircuitBreakerMiddleware
{
    /** @var callable(): float */
    private $clock;

    public function __construct(
        private Config $config,
        private RequestContextScope $contextScope,
        private CircuitBreakerRegistry $registry,
        private CircuitBreakerMetrics $metrics,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): float => microtime(true);
    }

    public function handle(Request $request, \Closure $next): ResponseInterface
    {
        $context = $this->contextScope->getOrCreate();
        $route = $context->get('route', sprintf('%s %s', $request->getMethod(), $request->getUri()->getPath()));
        $method = $request->getMethod();
        $settings = $this->resolveSettings($method, $route, $request);

        if ($settings['enabled'] === false || $settings['failure_threshold'] <= 0) {
            return $next($request);
        }

        $state = $this->registry->get($route);
        $now = ($this->clock)();

        if ($state['state'] === 'open') {
            $elapsed = $state['opened_at'] === null ? 0.0 : max(0.0, $now - $state['opened_at']);
            if ($elapsed < $settings['cooldown_seconds']) {
                return $this->reject($method, $route, $settings['cooldown_seconds'] - $elapsed);
            }

            $state['state'] = 'half_open';
            $state['successes'] = 0;
            $this->registry->set($route, $state);
            $this->metrics->recordHalfOpen($method, $route);
        }

        try {
            $response = $next($request);
            $failed = $response->getStatusCode() >= 500;
        } catch (\Throwable $exception) {
            $this->recordFailure($method, $route, $state, $settings, $now);
            $this->registry->set($route, $state);
            throw $exception;
        }

        if ($failed) {
            $this->recordFailure($method, $route, $state, $settings, $now);
            $this->registry->set($route, $state);
            return $response;
        }

        $state['failures'] = 0;
        $state['opened_at'] = null;

        if ($state['state'] === 'half_open') {
            $state['successes']++;
            if ($state['successes'] >= $settings['success_threshold']) {
                $state['state'] = 'closed';
                $state['successes'] = 0;
                $this->metrics->recordClosed($method, $route);
            } else {
                $this->metrics->recordHalfOpen($method, $route);
            }
        } else {
            $state['state'] = 'closed';
            $this->metrics->recordClosed($method, $route);
        }

        $this->registry->set($route, $state);

        return $response;
    }

    /**
     * @param array{state: string, failures: int, successes: int, opened_at: float|null} $state
     * @param array{enabled: bool, failure_threshold: int, cooldown_seconds: float, success_threshold: int} $settings
     */
    private function recordFailure(string $method, string $route, array &$state, array $settings, float $now): void
    {
        $this->metrics->recordFailure($method, $route);

        $state['failures']++;

        if ($state['state'] === 'half_open') {
            $state['state'] = 'open';
            $state['opened_at'] = $now;
            $state['successes'] = 0;
            $this->metrics->recordOpen($method, $route);
            return;
        }

        if ($state['failures'] >= $settings['failure_threshold']) {
            $state['state'] = 'open';
            $state['opened_at'] = $now;
            $state['successes'] = 0;
            $this->metrics->recordOpen($method, $route);
        }
    }

    /**
     * @return array{enabled: bool, failure_threshold: int, cooldown_seconds: float, success_threshold: int}
     */
    private function resolveSettings(string $method, string $routeSignature, Request $request): array
    {
        $config = $this->config->get('resilience.circuit_breaker');
        $defaults = [
            'enabled' => true,
            'failure_threshold' => 5,
            'cooldown_seconds' => 30.0,
            'success_threshold' => 1,
            'per_route' => [],
        ];

        if (!is_array($config)) {
            $config = $defaults;
        } else {
            $config = array_replace($defaults, $config);
        }

        $settings = [
            'enabled' => (bool) ($config['enabled'] ?? true),
            'failure_threshold' => (int) ($config['failure_threshold'] ?? 5),
            'cooldown_seconds' => (float) ($config['cooldown_seconds'] ?? 30.0),
            'success_threshold' => (int) ($config['success_threshold'] ?? 1),
        ];

        $perRoute = is_array($config['per_route'] ?? null) ? $config['per_route'] : [];
        $candidates = [
            $routeSignature,
            sprintf('%s %s', strtoupper($method), $request->getUri()->getPath()),
            $request->getUri()->getPath(),
        ];

        foreach ($candidates as $key) {
            if ($key === '' || !array_key_exists($key, $perRoute)) {
                continue;
            }

            $overrides = $perRoute[$key];
            if (is_int($overrides)) {
                $settings['failure_threshold'] = max(1, $overrides);
                continue;
            }

            if (!is_array($overrides)) {
                continue;
            }

            if (array_key_exists('enabled', $overrides)) {
                $settings['enabled'] = (bool) $overrides['enabled'];
            }

            if (array_key_exists('failure_threshold', $overrides) && is_int($overrides['failure_threshold'])) {
                $settings['failure_threshold'] = max(1, $overrides['failure_threshold']);
            }

            if (array_key_exists('cooldown_seconds', $overrides) && is_numeric($overrides['cooldown_seconds'])) {
                $settings['cooldown_seconds'] = max(0.0, (float) $overrides['cooldown_seconds']);
            }

            if (array_key_exists('success_threshold', $overrides) && is_int($overrides['success_threshold'])) {
                $settings['success_threshold'] = max(1, $overrides['success_threshold']);
            }
        }

        return $settings;
    }

    private function reject(string $method, string $route, float $remaining): ResponseInterface
    {
        $this->metrics->markOpenState($method, $route);

        $retryAfter = max(0, (int) ceil($remaining));
        $payload = [
            'error' => 'Service Unavailable',
            'message' => 'Circuit breaker open; request short-circuited.',
            'retry_after' => $retryAfter,
            'route' => $route,
        ];

        return new Response(
            503,
            [
                'Content-Type' => 'application/json',
                'Retry-After' => (string) $retryAfter,
            ],
            (string) json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }
}
