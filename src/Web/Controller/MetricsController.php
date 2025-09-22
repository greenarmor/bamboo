<?php

declare(strict_types=1);

namespace Bamboo\Web\Controller;

use Nyholm\Psr7\Response;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Prometheus\RenderTextFormat;
use Psr\Http\Message\ResponseInterface;

class MetricsController
{
    public function __construct(private CollectorRegistry $registry, private RenderTextFormat $renderer)
    {
    }

    public function index(): ResponseInterface
    {
        try {
            $metrics = $this->registry->getMetricFamilySamples();
        } catch (StorageException $exception) {
            return new Response(
                503,
                ['Content-Type' => 'text/plain; charset=utf-8'],
                "metrics collection unavailable: {$exception->getMessage()}\n"
            );
        }

        $body = $this->renderer->render($metrics);

        return new Response(
            200,
            ['Content-Type' => RenderTextFormat::MIME_TYPE],
            $body
        );
    }
}
