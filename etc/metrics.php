<?php

declare(strict_types=1);

/**
 * Metrics configuration for Prometheus instrumentation.
 *
 * The `storage` section selects the adapter used by the Prometheus client.
 * Supported drivers today include:
 *
 *  - `swoole_table` (default) – stores counters, gauges, and histograms inside
 *    an OpenSwoole shared table so worker processes observe a consistent view of
 *    the metrics.
 *  - `in_memory` – per-process storage that is useful for testing or
 *    environments without OpenSwoole available.
 *  - `apcu` – uses APCu shared memory (requires the extension to be loaded).
 *
 * Histogram buckets can be overridden per metric by specifying the fully
 * qualified metric name. When no explicit entry exists, the `default` bucket
 * definition is used.
 *
 * @return array{
 *     namespace: string,
 *     storage: array{
 *         driver: string,
 *         swoole_table?: array{value_rows?: int, string_rows?: int, string_size?: int},
 *         apcu?: array{prefix?: string}
 *     },
 *     histogram_buckets: array<string, array<int, float>>
 * }
 */
return [
    'namespace' => 'bamboo',
    'storage' => [
        'driver' => $_ENV['BAMBOO_METRICS_STORAGE'] ?? 'swoole_table',
        'swoole_table' => [
            'value_rows' => 16384,
            'string_rows' => 2048,
            'string_size' => 4096,
        ],
        'apcu' => [
            'prefix' => $_ENV['BAMBOO_METRICS_APCU_PREFIX'] ?? 'bamboo_prom',
        ],
    ],
    'histogram_buckets' => [
        'default' => [0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0],
        'bamboo_http_request_duration_seconds' => [0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0],
    ],
];
