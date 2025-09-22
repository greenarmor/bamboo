<?php

declare(strict_types=1);

namespace Bamboo\Observability\Metrics\Storage;

use Prometheus\Counter;
use Prometheus\Exception\StorageException;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Math;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\Adapter;
use Prometheus\Summary;
use RuntimeException;

class SwooleTableAdapter implements Adapter
{
    /**
     * Table column used for numeric values.
     */
    private const COLUMN_VALUE = 'value';

    /**
     * Table column used for string payloads (metadata, indexes, summaries).
     */
    private const COLUMN_PAYLOAD = 'payload';

    /**
     * @var \OpenSwoole\Table
     */
    private $valueTable;

    /**
     * @var \OpenSwoole\Table
     */
    private $stringTable;

    public function __construct(int $valueRows = 16384, int $stringRows = 2048, int $stringSize = 4096)
    {
        if (!class_exists('\\OpenSwoole\\Table')) {
            throw new StorageException('OpenSwoole extension is required for the SwooleTableAdapter.');
        }

        $this->valueTable = new \OpenSwoole\Table($valueRows);
        $this->valueTable->column(self::COLUMN_VALUE, \OpenSwoole\Table::TYPE_FLOAT);
        $this->valueTable->create();

        $this->stringTable = new \OpenSwoole\Table($stringRows);
        $this->stringTable->column(self::COLUMN_PAYLOAD, \OpenSwoole\Table::TYPE_STRING, $stringSize);
        $this->stringTable->create();
    }

    public function collect(bool $sortMetrics = true): array
    {
        $metrics = [];
        $metrics = array_merge($metrics, $this->collectScalarMetrics(Counter::TYPE, $sortMetrics));
        $metrics = array_merge($metrics, $this->collectScalarMetrics(Gauge::TYPE, $sortMetrics));
        $metrics = array_merge($metrics, $this->collectHistograms());
        $metrics = array_merge($metrics, $this->collectSummaries());

        return $metrics;
    }

    public function updateSummary(array $data): void
    {
        $metaKey = $this->metaKey($data);
        $this->ensureMeta($metaKey, $data);

        $valueKey = $this->valueKey($data);
        $samples = $this->readSummarySamples($valueKey);
        $samples[] = [
            'time' => time(),
            'value' => $data['value'],
        ];

        $this->writeSummarySamples($metaKey, $valueKey, $samples);
    }

    public function updateHistogram(array $data): void
    {
        $metaKey = $this->metaKey($data);
        $this->ensureMeta($metaKey, $data);

        $sumKey = $this->histogramBucketValueKey($data, 'sum');
        $this->registerSampleKey($metaKey, $sumKey);
        $this->incrementValue($sumKey, (float) $data['value']);

        $bucketToIncrease = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = (string) $bucket;
                break;
            }
        }

        $bucketKey = $this->histogramBucketValueKey($data, $bucketToIncrease);
        $this->registerSampleKey($metaKey, $bucketKey);
        $this->incrementValue($bucketKey, 1.0);
    }

    public function updateGauge(array $data): void
    {
        $metaKey = $this->metaKey($data);
        $this->ensureMeta($metaKey, $data);

        $valueKey = $this->valueKey($data);
        $this->registerSampleKey($metaKey, $valueKey);

        if ($data['command'] === Adapter::COMMAND_SET) {
            $this->setValue($valueKey, (float) $data['value']);
            return;
        }

        $this->incrementValue($valueKey, (float) $data['value']);
    }

    public function updateCounter(array $data): void
    {
        $metaKey = $this->metaKey($data);
        $this->ensureMeta($metaKey, $data);

        $valueKey = $this->valueKey($data);
        $this->registerSampleKey($metaKey, $valueKey);

        if ($data['command'] === Adapter::COMMAND_SET) {
            $this->setValue($valueKey, 0.0);
            return;
        }

        $this->incrementValue($valueKey, (float) $data['value']);
    }

    public function wipeStorage(): void
    {
        foreach ($this->valueTable as $key => $_row) {
            $this->valueTable->del($key);
        }

        foreach ($this->stringTable as $key => $_row) {
            $this->stringTable->del($key);
        }
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectScalarMetrics(string $type, bool $sortMetrics): array
    {
        $results = [];
        foreach ($this->getIndex($type) as $metaKey) {
            $meta = $this->readMeta($metaKey);
            if ($meta === null) {
                continue;
            }

            $data = [
                'name' => $meta['name'],
                'help' => $meta['help'],
                'type' => $meta['type'],
                'labelNames' => $meta['labelNames'],
                'samples' => [],
            ];

            foreach ($this->getSampleKeys($metaKey) as $sampleKey) {
                $parts = explode(':', $sampleKey);
                if (count($parts) < 4) {
                    continue;
                }

                $encodedLabels = $parts[2];
                $value = $this->readValue($sampleKey);
                $data['samples'][] = [
                    'name' => $meta['name'],
                    'labelNames' => [],
                    'labelValues' => $this->decodeLabelValues($encodedLabels),
                    'value' => $value,
                ];
            }

            if ($sortMetrics) {
                $this->sortSamples($data['samples']);
            }

            $results[] = new MetricFamilySamples($data);
        }

        return $results;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectHistograms(): array
    {
        $results = [];
        foreach ($this->getIndex(Histogram::TYPE) as $metaKey) {
            $meta = $this->readMeta($metaKey);
            if ($meta === null) {
                continue;
            }

            $data = [
                'name' => $meta['name'],
                'help' => $meta['help'],
                'type' => $meta['type'],
                'labelNames' => $meta['labelNames'],
                'buckets' => $meta['buckets'],
                'samples' => [],
            ];

            $data['buckets'][] = '+Inf';
            $histogramBuckets = [];

            foreach ($this->getSampleKeys($metaKey) as $sampleKey) {
                $parts = explode(':', $sampleKey);
                if (count($parts) < 4) {
                    continue;
                }

                [$metricType, $metricName, $encodedLabels, $bucket] = $parts;
                if ($metricType !== Histogram::TYPE || $metricName !== $meta['name']) {
                    continue;
                }

                $histogramBuckets[$encodedLabels][$bucket] = $this->readValue($sampleKey);
            }

            $labelGroups = array_keys($histogramBuckets);
            sort($labelGroups);

            foreach ($labelGroups as $labelValues) {
                $acc = 0.0;
                $decodedLabelValues = $this->decodeLabelValues($labelValues);
                foreach ($data['buckets'] as $bucket) {
                    $bucketKey = (string) $bucket;
                    $count = $histogramBuckets[$labelValues][$bucketKey] ?? 0.0;
                    $acc += $count;
                    $data['samples'][] = [
                        'name' => $meta['name'] . '_bucket',
                        'labelNames' => ['le'],
                        'labelValues' => array_merge($decodedLabelValues, [$bucketKey]),
                        'value' => $acc,
                    ];
                }

                $data['samples'][] = [
                    'name' => $meta['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => $acc,
                ];

                $sum = $histogramBuckets[$labelValues]['sum'] ?? 0.0;
                $data['samples'][] = [
                    'name' => $meta['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => $sum,
                ];
            }

            $results[] = new MetricFamilySamples($data);
        }

        return $results;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectSummaries(): array
    {
        $math = new Math();
        $results = [];

        foreach ($this->getIndex(Summary::TYPE) as $metaKey) {
            $meta = $this->readMeta($metaKey);
            if ($meta === null) {
                continue;
            }

            $data = [
                'name' => $meta['name'],
                'help' => $meta['help'],
                'type' => $meta['type'],
                'labelNames' => $meta['labelNames'],
                'maxAgeSeconds' => $meta['maxAgeSeconds'],
                'quantiles' => $meta['quantiles'],
                'samples' => [],
            ];

            foreach ($this->getSampleKeys($metaKey) as $sampleKey) {
                $parts = explode(':', $sampleKey);
                if (count($parts) < 4) {
                    continue;
                }

                $encodedLabels = $parts[2];
                $values = $this->readSummarySamples($sampleKey);
                $values = array_filter($values, static fn(array $value): bool => (time() - $value['time']) <= $meta['maxAgeSeconds']);

                if ($values === []) {
                    $this->removeSummarySample($metaKey, $sampleKey);
                    continue;
                }

                usort($values, static function (array $left, array $right): int {
                    return $left['value'] <=> $right['value'];
                });

                $decodedLabelValues = $this->decodeLabelValues($encodedLabels);
                foreach ($data['quantiles'] as $quantile) {
                    $data['samples'][] = [
                        'name' => $meta['name'],
                        'labelNames' => ['quantile'],
                        'labelValues' => array_merge($decodedLabelValues, [$quantile]),
                        'value' => $math->quantile(array_column($values, 'value'), $quantile),
                    ];
                }

                $data['samples'][] = [
                    'name' => $meta['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => count($values),
                ];

                $data['samples'][] = [
                    'name' => $meta['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => array_sum(array_column($values, 'value')),
                ];

                $this->writeSummarySamples($metaKey, $sampleKey, $values);
            }

            if ($data['samples'] !== []) {
                $results[] = new MetricFamilySamples($data);
            }
        }

        return $results;
    }

    private function ensureMeta(string $metaKey, array $data): void
    {
        if ($this->readMeta($metaKey) !== null) {
            return;
        }

        $meta = $this->metaData($data);
        $this->writeMeta($metaKey, $meta);
        $this->appendIndex($meta['type'], $metaKey);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMeta(string $metaKey): ?array
    {
        $payload = $this->readString($metaKey);
        if ($payload === null || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function writeMeta(string $metaKey, array $meta): void
    {
        $this->writeString($metaKey, $this->encodeJson($meta));
    }

    private function appendIndex(string $type, string $metaKey): void
    {
        $indexKey = $this->indexKey($type);
        $entries = $this->getIndex($type);
        if (!in_array($metaKey, $entries, true)) {
            $entries[] = $metaKey;
            $this->writeString($indexKey, $this->encodeJson($entries));
        }
    }

    /**
     * @return string[]
     */
    private function getIndex(string $type): array
    {
        $indexKey = $this->indexKey($type);
        $payload = $this->readString($indexKey);
        if ($payload === null || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn($entry) => is_string($entry) && $entry !== ''));
    }

    private function registerSampleKey(string $metaKey, string $sampleKey): void
    {
        $indexKey = $this->sampleIndexKey($metaKey);
        $entries = $this->getSampleKeys($metaKey);
        if (!in_array($sampleKey, $entries, true)) {
            $entries[] = $sampleKey;
            $this->writeString($indexKey, $this->encodeJson($entries));
        }
    }

    /**
     * @return string[]
     */
    private function getSampleKeys(string $metaKey): array
    {
        $indexKey = $this->sampleIndexKey($metaKey);
        $payload = $this->readString($indexKey);
        if ($payload === null || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn($entry) => is_string($entry) && $entry !== ''));
    }

    private function removeSummarySample(string $metaKey, string $sampleKey): void
    {
        $this->stringTable->del($sampleKey);
        $entries = $this->getSampleKeys($metaKey);
        $updated = array_values(array_filter($entries, static fn(string $entry): bool => $entry !== $sampleKey));
        $this->writeString($this->sampleIndexKey($metaKey), $this->encodeJson($updated));
    }

    private function incrementValue(string $key, float $value): float
    {
        if (!$this->valueTable->exists($key)) {
            $this->valueTable->set($key, [self::COLUMN_VALUE => 0.0]);
        }

        $this->valueTable->incr($key, self::COLUMN_VALUE, $value);
        return $this->readValue($key);
    }

    private function setValue(string $key, float $value): void
    {
        $this->valueTable->set($key, [self::COLUMN_VALUE => $value]);
    }

    private function readValue(string $key): float
    {
        $row = $this->valueTable->get($key);
        if ($row === false || !is_array($row)) {
            return 0.0;
        }

        return (float) ($row[self::COLUMN_VALUE] ?? 0.0);
    }

    /**
     * @return array<int, array{time: int, value: float}>
     */
    private function readSummarySamples(string $key): array
    {
        $payload = $this->readString($key);
        if ($payload === null || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_map(static function ($value): array {
            if (!is_array($value)) {
                return ['time' => time(), 'value' => 0.0];
            }

            return [
                'time' => isset($value['time']) ? (int) $value['time'] : time(),
                'value' => isset($value['value']) ? (float) $value['value'] : 0.0,
            ];
        }, $decoded);
    }

    private function writeSummarySamples(string $metaKey, string $valueKey, array $samples): void
    {
        if ($samples === []) {
            $this->removeSummarySample($metaKey, $valueKey);
            return;
        }

        $this->registerSampleKey($metaKey, $valueKey);
        $this->writeString($valueKey, $this->encodeJson($samples));
    }

    private function readString(string $key): ?string
    {
        $row = $this->stringTable->get($key);
        if ($row === false || !is_array($row) || !array_key_exists(self::COLUMN_PAYLOAD, $row)) {
            return null;
        }

        $value = $row[self::COLUMN_PAYLOAD];
        return $value === '' ? null : (string) $value;
    }

    private function writeString(string $key, string $value): void
    {
        $this->stringTable->set($key, [self::COLUMN_PAYLOAD => $value]);
    }

    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value);
        if ($encoded === false) {
            throw new RuntimeException(json_last_error_msg());
        }

        return $encoded;
    }

    private function metaKey(array $data): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            'meta',
        ]);
    }

    private function valueKey(array $data): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            'value',
        ]);
    }

    private function histogramBucketValueKey(array $data, string $bucket): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            $bucket,
        ]);
    }

    private function indexKey(string $type): string
    {
        return 'index:' . $type;
    }

    private function sampleIndexKey(string $metaKey): string
    {
        return 'samples:' . $metaKey;
    }

    private function metaData(array $data): array
    {
        $meta = $data;
        unset($meta['value'], $meta['command'], $meta['labelValues']);
        return $meta;
    }

    private function sortSamples(array &$samples): void
    {
        usort($samples, static function (array $a, array $b): int {
            return strcmp(implode('', $a['labelValues']), implode('', $b['labelValues']));
        });
    }

    private function encodeLabelValues(array $values): string
    {
        $json = json_encode($values);
        if ($json === false) {
            throw new RuntimeException(json_last_error_msg());
        }

        return base64_encode($json);
    }

    private function decodeLabelValues(string $values): array
    {
        $json = base64_decode($values, true);
        if ($json === false) {
            throw new RuntimeException('Cannot base64 decode label values');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(json_last_error_msg());
        }

        return $decoded;
    }
}
