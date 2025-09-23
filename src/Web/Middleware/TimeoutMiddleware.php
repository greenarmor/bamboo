<?php

declare(strict_types=1);

namespace Bamboo\Web\Middleware;

use Bamboo\Core\Config;
use Bamboo\Observability\Metrics\HttpMetrics;
use Bamboo\Web\RequestContext;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class TimeoutMiddleware
{
    public function __construct(
        private Config $config,
        private RequestContext $context,
        private HttpMetrics $metrics,
    ) {
    }

    public function handle(Request $request, \Closure $next): ResponseInterface
    {
        $route = $this->context->get('route', sprintf('%s %s', $request->getMethod(), $request->getUri()->getPath()));
        $method = $request->getMethod();
        $threshold = $this->resolveThreshold($method, $route, $request);

        if ($threshold === null || $threshold <= 0.0) {
            return $next($request);
        }

        $start = microtime(true);

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $elapsed = microtime(true) - $start;
            $this->context->set('timeout.elapsed', $elapsed);
            throw $exception;
        }

        $elapsed = microtime(true) - $start;
        $this->context->set('timeout.elapsed', $elapsed);
        $this->context->set('timeout.threshold', $threshold);

        if ($elapsed > $threshold) {
            $this->metrics->incrementTimeout($method, $route);

            $payload = [
                'error' => 'Gateway Timeout',
                'message' => sprintf('Request exceeded %.3f second limit.', $threshold),
                'timeout' => $threshold,
                'elapsed' => $elapsed,
                'route' => $route,
            ];

            return new Response(
                504,
                [
                    'Content-Type' => 'application/json',
                    'X-Bamboo-Timeout' => sprintf('%.3f', $threshold),
                ],
                (string) json_encode($payload, JSON_THROW_ON_ERROR)
            );
        }

        return $response->withHeader('X-Bamboo-Elapsed', sprintf('%.3f', $elapsed));
    }

    private function resolveThreshold(string $method, string $routeSignature, Request $request): ?float
    {
        $config = $this->config->get('resilience.timeouts') ?? [];
        if (!is_array($config)) {
            return null;
        }

        $perRoute = isset($config['per_route']) && is_array($config['per_route']) ? $config['per_route'] : [];

        $candidates = [
            $routeSignature,
            sprintf('%s %s', strtoupper($method), $request->getUri()->getPath()),
            $request->getUri()->getPath(),
        ];

        foreach ($candidates as $key) {
            if ($key === '') {
                continue;
            }

            if (!array_key_exists($key, $perRoute)) {
                continue;
            }

            $value = $this->extractTimeout($perRoute[$key]);
            if ($value !== null) {
                return $value;
            }
        }

        if (isset($config['default'])) {
            $value = $this->extractTimeout($config['default']);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function extractTimeout(mixed $value): ?float
    {
        if (is_numeric($value)) {
            $timeout = (float) $value;
            return $timeout > 0.0 ? $timeout : null;
        }

        if (is_array($value) && isset($value['timeout']) && is_numeric($value['timeout'])) {
            $timeout = (float) $value['timeout'];
            return $timeout > 0.0 ? $timeout : null;
        }

        return null;
    }
}
