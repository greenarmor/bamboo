<?php

namespace Tests\Roadmap\V0_4;

use PHPUnit\Framework\TestCase;

class MetricsCollectionTest extends TestCase {
  public function testRequestMetricsAreRecordedAcrossWorkers(): void {
    $this->markTestIncomplete('v0.4: share Prometheus collector across OpenSwoole workers so counters aggregate globally.');
  }

  public function testLatencyHistogramUsesConfigurableBuckets(): void {
    $this->markTestIncomplete('v0.4: allow latency histogram buckets to be configured via etc/metrics.php.');
  }
}
