<?php

namespace Tests\Roadmap\V0_4;

use PHPUnit\Framework\TestCase;

class CircuitBreakerMiddlewareTest extends TestCase {
  public function testCircuitBreakerOpensAfterFailureThreshold(): void {
    $this->markTestIncomplete(
      'v0.4: CircuitBreakerMiddleware should open after failure threshold and return 503 responses while short-circuited.'
    );
  }

  public function testCircuitBreakerPublishesStateMetrics(): void {
    $this->markTestIncomplete(
      'v0.4: CircuitBreakerMiddleware must publish Prometheus metrics for state transitions and failure counters.'
    );
  }
}
