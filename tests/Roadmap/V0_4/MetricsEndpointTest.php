<?php

namespace Tests\Roadmap\V0_4;

use PHPUnit\Framework\TestCase;

class MetricsEndpointTest extends TestCase {
  public function testMetricsEndpointExposesPrometheusTextFormat(): void {
    $this->markTestIncomplete('v0.4: implement /metrics endpoint returning Prometheus text format with HELP/TYPE preamble.');
  }

  public function testMetricsEndpointReturns503WhenCollectorUnavailable(): void {
    $this->markTestIncomplete('v0.4: ensure /metrics emits 503 with explanatory body when metrics storage is unavailable.');
  }
}
