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

    private const PREFIX_META = 'meta:';

    private const PREFIX_SAMPLE = 'sample:';

    private const PREFIX_SAMPLES = 'samples:';

    private const PREFIX_LABELS = 'labels:';

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
                $parsed = $this->parseSampleKey($sampleKey);
                if ($parsed === null || $parsed['suffix'] !== 'value') {
                    continue;
                }

                $value = $this->readValue($sampleKey);
                $data['samples'][] = [
                    'name' => $meta['name'],
                    'labelNames' => [],
                    'labelValues' => $this->decodeLabelValues($parsed['labels']),
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
                $parsed = $this->parseSampleKey($sampleKey);
                if ($parsed === null || !str_starts_with($parsed['suffix'], 'bucket:')) {
                    continue;
                }

                $bucket = substr($parsed['suffix'], strlen('bucket:'));
                $histogramBuckets[$parsed['labels']][$bucket] = $this->readValue($sampleKey);
            }

            ksort($histogramBuckets);

            foreach ($histogramBuckets as $labelIdentifier => $bucketValues) {
                $acc = 0.0;
                $decodedLabelValues = $this->decodeLabelValues($labelIdentifier);
                foreach ($data['buckets'] as $bucket) {
                    $bucketKey = (string) $bucket;
                    $count = $bucketValues[$bucketKey] ?? 0.0;
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

                $sum = $bucketValues['sum'] ?? 0.0;
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
                $parsed = $this->parseSampleKey($sampleKey);
                if ($parsed === null || $parsed['suffix'] !== 'value') {
                    continue;
                }

                $values = $this->readSummarySamples($sampleKey);
                $values = array_filter($values, static fn(array $value): bool => (time() - $value['time']) <= $meta['maxAgeSeconds']);

                if ($values === []) {
                    $this->removeSummarySample($metaKey, $sampleKey);
                    continue;
                }

                usort($values, static function (array $left, array $right): int {
                    return $left['value'] <=> $right['value'];
                });

                $decodedLabelValues = $this->decodeLabelValues($parsed['labels']);
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

    /**
     * @param array<string, mixed> $data
     */
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

    /**
     * @param array<string, mixed> $meta
     */
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

    /**
     * @param array<int, array{time: int, value: float}> $samples
     */
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

    /**
     * @param array<int|string, mixed> $value
     */
    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value);
        if ($encoded === false) {
            throw new RuntimeException(json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function metaKey(array $data): string
    {
        return self::PREFIX_META . $this->metricIdentifier($data['type'], $data['name']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function valueKey(array $data): string
    {
        $metricId = $this->metricIdentifier($data['type'], $data['name']);
        $labelIdentifier = $this->encodeLabelValues($data['labelValues']);

        return self::PREFIX_SAMPLE . $metricId . ':' . $labelIdentifier . ':value';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function histogramBucketValueKey(array $data, string $bucket): string
    {
        $metricId = $this->metricIdentifier($data['type'], $data['name']);
        $labelIdentifier = $this->encodeLabelValues($data['labelValues']);

        return self::PREFIX_SAMPLE . $metricId . ':' . $labelIdentifier . ':bucket:' . $bucket;
    }

    private function indexKey(string $type): string
    {
        return 'index:' . $type;
    }

    private function sampleIndexKey(string $metaKey): string
    {
        return self::PREFIX_SAMPLES . $this->metricIdentifierFromMetaKey($metaKey);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function metaData(array $data): array
    {
        $meta = $data;
        unset($meta['value'], $meta['command'], $meta['labelValues']);
        return $meta;
    }

    /**
     * @param array<int, array{name: string, labelNames: array<int, string>, labelValues: array<int|string, scalar>, value: float|int}> $samples
     */
    private function sortSamples(array &$samples): void
    {
        usort($samples, static function (array $a, array $b): int {
            return strcmp(implode('', $a['labelValues']), implode('', $b['labelValues']));
        });
    }

    /**
     * @param array<int|string, scalar|null> $values
     */
    private function encodeLabelValues(array $values): string
    {
        $json = $this->encodeJson($values);
        $hash = substr(hash('sha1', $json), 0, 16);
        $storageKey = $this->labelStorageKey($hash);

        if (!$this->stringTable->exists($storageKey)) {
            $this->stringTable->set($storageKey, [self::COLUMN_PAYLOAD => $json]);
        }

        return $hash;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeLabelValues(string $identifier): array
    {
        $key = $this->labelStorageKey($identifier);
        $row = $this->stringTable->get($key);
        if (!is_array($row) || !array_key_exists(self::COLUMN_PAYLOAD, $row)) {
            throw new RuntimeException('Cannot read stored label values for identifier: ' . $identifier);
        }

        $decoded = json_decode($row[self::COLUMN_PAYLOAD], true);
        if (!is_array($decoded)) {
            throw new RuntimeException(json_last_error_msg());
        }

        return $decoded;
    }

    private function labelStorageKey(string $hash): string
    {
        return self::PREFIX_LABELS . $hash;
    }

    private function metricIdentifier(string $type, string $name): string
    {
        return substr(hash('sha1', $type . ':' . $name), 0, 16);
    }

    private function metricIdentifierFromMetaKey(string $metaKey): string
    {
        if (str_starts_with($metaKey, self::PREFIX_META)) {
            return substr($metaKey, strlen(self::PREFIX_META));
        }

        return $metaKey;
    }

    /**
     * @return array{metric: string, labels: string, suffix: string}|null
     */
    private function parseSampleKey(string $sampleKey): ?array
    {
        if (!str_starts_with($sampleKey, self::PREFIX_SAMPLE)) {
            return null;
        }

        $parts = explode(':', $sampleKey, 4);
        if (count($parts) < 4 || $parts[0] !== 'sample') {
            return null;
        }

        return [
            'metric' => $parts[1],
            'labels' => $parts[2],
            'suffix' => $parts[3],
        ];
    }
}
