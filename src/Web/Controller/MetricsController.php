<?php

declare(strict_types=1);

namespace Bamboo\Web\Controller;

use Bamboo\Core\Application;
use Nyholm\Psr7\Response;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Prometheus\RenderTextFormat;
use Psr\Http\Message\ResponseInterface;

class MetricsController
{
    public function __construct(private Application $app)
    {
    }

    public function index(): ResponseInterface
    {
        if (!$this->app->has(CollectorRegistry::class) || !$this->app->has(RenderTextFormat::class)) {
            return new Response(
                503,
                ['Content-Type' => 'text/plain; charset=utf-8'],
                "metrics collection unavailable: dependencies missing\n"
            );
        }

        /** @var CollectorRegistry $registry */
        $registry = $this->app->get(CollectorRegistry::class);

        /** @var RenderTextFormat $renderer */
        $renderer = $this->app->get(RenderTextFormat::class);

        try {
            $metrics = $registry->getMetricFamilySamples();
        } catch (StorageException $exception) {
            return new Response(
                503,
                ['Content-Type' => 'text/plain; charset=utf-8'],
                "metrics collection unavailable: {$exception->getMessage()}\n"
            );
        }

        $body = $renderer->render($metrics);

        return new Response(
            200,
            ['Content-Type' => RenderTextFormat::MIME_TYPE],
            $body
        );
    }
}
