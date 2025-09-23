<?php

namespace Tests\Roadmap\V0_4;

use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Observability\Metrics\HttpMetrics;
use Bamboo\Provider\AppProvider;
use Bamboo\Provider\MetricsProvider;
use Bamboo\Provider\ResilienceProvider;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Tests\Support\ArrayConfig;

class MetricsCollectionTest extends TestCase
{
    public function testRequestMetricsAreRecordedAcrossWorkers(): void
    {
        $app = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        $workerOne = clone $app;
        $workerTwo = clone $app;

        $metricsOne = $workerOne->get(HttpMetrics::class);
        $timerOne = $metricsOne->startTimer('GET', '/tasks');
        $timerOne['started'] = microtime(true) - 0.2;
        $metricsOne->observeResponse('GET', '/tasks', 200, $timerOne);

        $metricsTwo = $workerTwo->get(HttpMetrics::class);
        $timerTwo = $metricsTwo->startTimer('POST', '/tasks');
        $timerTwo['started'] = microtime(true) - 0.4;
        $metricsTwo->observeResponse('POST', '/tasks', 500, $timerTwo);

        $registry = $app->get(CollectorRegistry::class);

        $totals = [];
        foreach ($registry->getMetricFamilySamples() as $family) {
            if ($family->getName() !== 'bamboo_http_requests_total') {
                continue;
            }

            foreach ($family->getSamples() as $sample) {
                $labels = $sample->getLabelValues();
                if (count($labels) < 3) {
                    continue;
                }

                [$method, $route, $status] = array_slice($labels, 0, 3);
                if ($route !== '/tasks') {
                    continue;
                }

                $totals[$method . ':' . $status] = (float) $sample->getValue();
            }
        }

        $this->assertSame(1.0, $totals['GET:200'] ?? 0.0);
        $this->assertSame(1.0, $totals['POST:500'] ?? 0.0);
    }

    public function testLatencyHistogramUsesConfigurableBuckets(): void
    {
        $baseConfig = new Config(dirname(__DIR__, 3) . '/etc');
        $configuration = $baseConfig->all();
        $configuration['metrics']['histogram_buckets']['bamboo_http_request_duration_seconds'] = [0.1, 0.3];

        $app = new Application(new ArrayConfig($configuration));
        $app->register(new AppProvider());
        $app->register(new MetricsProvider());
        $app->register(new ResilienceProvider());
        $app->bootModules([]);

        $metrics = $app->get(HttpMetrics::class);
        $adapter = $app->get(Adapter::class);
        $adapter->wipeStorage();

        $timer = $metrics->startTimer('GET', '/metrics');
        $timer['started'] = microtime(true) - 0.25;
        $metrics->observeResponse('GET', '/metrics', 200, $timer);

        $registry = $app->get(CollectorRegistry::class);

        $bucketBounds = [];
        foreach ($registry->getMetricFamilySamples() as $family) {
            if ($family->getName() !== 'bamboo_http_request_duration_seconds') {
                continue;
            }

            foreach ($family->getSamples() as $sample) {
                if ($sample->getName() !== 'bamboo_http_request_duration_seconds_bucket') {
                    continue;
                }

                $labels = $sample->getLabelValues();
                $bucketBounds[] = $labels[count($labels) - 1];
            }
        }

        $this->assertSame(['0.1', '0.3', '+Inf'], $bucketBounds);
    }
}
