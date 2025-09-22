<?php

namespace Tests\Roadmap\V0_4;

use PHPUnit\Framework\TestCase;

class TimeoutMiddlewareTest extends TestCase {
  public function testTimeoutMiddlewareAbortsLongRunningRequests(): void {
    $this->markTestIncomplete(
      'v0.4: add TimeoutMiddleware that aborts requests exceeding configured thresholds and returns 504 responses.'
    );
  }

  public function testTimeoutMiddlewareRecordsTimeoutMetrics(): void {
    $this->markTestIncomplete(
      'v0.4: TimeoutMiddleware should increment bamboo_http_timeouts_total via shared metrics collector.'
    );
  }
}
