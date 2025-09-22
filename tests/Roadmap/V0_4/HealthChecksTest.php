<?php

namespace Tests\Roadmap\V0_4;

use PHPUnit\Framework\TestCase;

class HealthChecksTest extends TestCase {
  public function testHealthzEndpointReportsLiveness(): void {
    $this->markTestIncomplete('v0.4: add /healthz endpoint that returns 200 while worker loop is running.');
  }

  public function testReadyzEndpointReflectsDependencyStatus(): void {
    $this->markTestIncomplete('v0.4: add /readyz endpoint that returns 200 only when dependencies are healthy.');
  }

  public function testGracefulShutdownMarksWorkerUnreadyBeforeExit(): void {
    $this->markTestIncomplete('v0.4: integrate OpenSwoole shutdown hooks to flip readiness to false before stopping workers.');
  }
}
