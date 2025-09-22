<?php

declare(strict_types=1);

namespace Tests\Observability;

use Bamboo\Observability\Metrics\HttpMetrics;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;

class HttpMetricsTest extends TestCase
{
    public function testDurationHistogramUsesNamespaceSpecificBuckets(): void
    {
        $registry = new CollectorRegistry(new InMemory());
        $config = [
            'namespace' => 'custom',
            'histogram_buckets' => [
                'default' => [99.0],
                'custom_http_request_duration_seconds' => [0.2, 0.4],
            ],
        ];

        $metrics = new HttpMetrics($registry, $config);
        $timer = $metrics->startTimer('GET', '/metrics');
        $timer['started'] = microtime(true) - 0.3;
        $metrics->observeResponse('GET', '/metrics', 200, $timer);

        $histogram = null;
        foreach ($registry->getMetricFamilySamples() as $metric) {
            if ($metric->getName() === 'custom_http_request_duration_seconds') {
                $histogram = $metric;
                break;
            }
        }

        $this->assertNotNull($histogram);

        $bucketBounds = [];
        foreach ($histogram->getSamples() as $sample) {
            if ($sample->getName() !== 'custom_http_request_duration_seconds_bucket') {
                continue;
            }

            $labelValues = $sample->getLabelValues();
            $bucketBounds[] = $labelValues[count($labelValues) - 1];
        }

        $this->assertSame(['0.2', '0.4', '+Inf'], $bucketBounds);
    }
}
