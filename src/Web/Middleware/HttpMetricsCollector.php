<?php

declare(strict_types=1);

namespace Bamboo\Web\Middleware;

use Bamboo\Observability\Metrics\HttpMetrics;
use Bamboo\Web\RequestContextScope;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class HttpMetricsCollector
{
    public function __construct(private HttpMetrics $metrics, private RequestContextScope $contextScope)
    {
    }

    public function handle(Request $request, \Closure $next): ResponseInterface
    {
        $context = $this->contextScope->getOrCreate();
        $method = $request->getMethod();
        $route = $context->get('route', sprintf('%s %s', $method, $request->getUri()->getPath()));

        $this->metrics->incrementInFlight($method, $route);
        $timer = $this->metrics->startTimer($method, $route);

        try {
            $response = $next($request);
            $this->metrics->observeResponse($method, $route, $response->getStatusCode(), $timer);
            return $response;
        } catch (\Throwable $exception) {
            $this->metrics->observeException($method, $route, $timer);
            throw $exception;
        } finally {
            $this->metrics->decrementInFlight($method, $route);
        }
    }
}
