<?php

declare(strict_types=1);

namespace Bamboo\Provider;

use Bamboo\Core\Application;
use Bamboo\Observability\Metrics\CircuitBreakerMetrics;
use Bamboo\Observability\Metrics\HttpMetrics;
use Bamboo\Observability\Metrics\Storage\SwooleTableAdapter;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\APC;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;

class MetricsProvider
{
    public function register(Application $app): void
    {
        $app->singleton(Adapter::class, function (Application $app) {
            $config = $app->config('metrics') ?? [];
            return $this->createAdapter($config);
        });

        $app->singleton(CollectorRegistry::class, function (Application $app) {
            /** @var Adapter $adapter */
            $adapter = $app->get(Adapter::class);
            return new CollectorRegistry($adapter);
        });

        $app->singleton(RenderTextFormat::class, static fn(): RenderTextFormat => new RenderTextFormat());

        $app->singleton(HttpMetrics::class, function (Application $app) {
            $config = $app->config('metrics') ?? [];
            $config['namespace'] = is_string($config['namespace'] ?? null) ? $config['namespace'] : 'bamboo';

            return new HttpMetrics($app->get(CollectorRegistry::class), $config);
        });

        $app->bind('metrics.http', fn(Application $app): HttpMetrics => $app->get(HttpMetrics::class));

        $app->singleton(CircuitBreakerMetrics::class, function (Application $app) {
            $config = $app->config('metrics') ?? [];

            return new CircuitBreakerMetrics($app->get(CollectorRegistry::class), $config);
        });

        $app->bind('metrics.circuit_breaker', fn(Application $app): CircuitBreakerMetrics => $app->get(CircuitBreakerMetrics::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createAdapter(array $config): Adapter
    {
        $storage = $config['storage'] ?? [];
        $driver = is_array($storage) ? (string) ($storage['driver'] ?? 'swoole_table') : 'swoole_table';

        switch ($driver) {
            case 'apcu':
                return $this->createApcAdapter($storage);
            case 'in_memory':
                return new InMemory();
            case 'swoole_table':
            default:
                return $this->createSwooleTableAdapter($storage);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createSwooleTableAdapter(array $config): Adapter
    {
        if (!class_exists('\\OpenSwoole\\Table')) {
            return new InMemory();
        }

        $options = is_array($config['swoole_table'] ?? null) ? $config['swoole_table'] : [];

        $valueRows = isset($options['value_rows']) ? (int) $options['value_rows'] : 16384;
        $stringRows = isset($options['string_rows']) ? (int) $options['string_rows'] : 2048;
        $stringSize = isset($options['string_size']) ? (int) $options['string_size'] : 4096;

        try {
            return new SwooleTableAdapter($valueRows, $stringRows, $stringSize);
        } catch (StorageException $exception) {
            return new InMemory();
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createApcAdapter(array $config): Adapter
    {
        if (!class_exists('APCuIterator')) {
            return new InMemory();
        }

        $prefix = '';
        if (isset($config['apcu']['prefix']) && is_string($config['apcu']['prefix'])) {
            $prefix = $config['apcu']['prefix'];
        }

        try {
            return new APC($prefix === '' ? APC::PROMETHEUS_PREFIX : $prefix);
        } catch (StorageException $exception) {
            return new InMemory();
        }
    }
}
