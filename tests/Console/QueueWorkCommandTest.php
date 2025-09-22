<?php

namespace Tests\Console;

use Bamboo\Console\Command\QueueWork;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\PredisFakeServer;
use Tests\Stubs\PredisMemoryConnection;
use Tests\Support\RouterTestApplication;

class QueueWorkCommandTest extends TestCase {
  protected function setUp(): void {
    parent::setUp();
    PredisFakeServer::reset();
  }

  public function testProcessesLimitedNumberOfJobs(): void {
    $app = new RouterTestApplication([], [
      'redis' => ['url' => 'memory://local', 'queue' => 'jobs'],
    ]);

    $app->singleton('redis.client.factory', function() use ($app) {
      return function(array $overrides = []) use ($app) {
        $config = array_replace($app->config('redis') ?? [], $overrides);
        $url = $config['url'] ?? 'memory://local';
        $options = $config['options'] ?? [];
        $options['connections']['memory'] = PredisMemoryConnection::factory();
        return new \Predis\Client($url, $options);
      };
    });

    $client = new \Predis\Client('memory://local', [
      'connections' => ['memory' => PredisMemoryConnection::factory()],
    ]);
    $client->rpush('jobs', [
      json_encode(['task' => 'one']),
      json_encode(['task' => 'two'])
    ]);

    $command = new QueueWork($app);

    ob_start();
    $exitCode = $command->handle(['--max-jobs=2']);
    $output = ob_get_clean();

    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString("Worker listening on 'jobs'", $output);
    $this->assertStringContainsString('Job: {"task":"one"}', $output);
    $this->assertStringContainsString('Job: {"task":"two"}', $output);
    $this->assertSame([], PredisFakeServer::dumpQueue('jobs'));
  }
}
