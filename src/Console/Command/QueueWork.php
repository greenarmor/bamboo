<?php
namespace Bamboo\Console\Command;

class QueueWork extends Command {
  public function name(): string { return 'queue.work'; }
  public function description(): string { return 'Start a Redis-backed worker (BLPOP)'; }
  /**
   * @param list<string> $args
   */
  public function handle(array $args): int {
    $cfg = $this->app->config('redis');
    $queue = $cfg['queue'];
    $maxJobs = $this->parseMaxJobs($args);
    $processed = 0;

    $factory = $this->app->get('redis.client.factory');
    $client = $factory();
    echo "Worker listening on '{$queue}'\n";

    while (true) {
      if ($maxJobs !== null && $processed >= $maxJobs) {
        break;
      }

      $item = $client->blpop([$queue], 30);
      if (!$item) {
        continue;
      }

      [, $payload] = $item;
      echo "Job: {$payload}\n";
      $processed++;
    }

    return 0;
  }

  /**
   * @param list<string> $args
   */
  private function parseMaxJobs(array $args): ?int {
    $maxJobs = null;

    foreach ($args as $index => $arg) {
      if ($arg === '--once') {
        $maxJobs = 1;
        continue;
      }

      if (str_starts_with($arg, '--max-jobs=')) {
        $value = substr($arg, 11);
        $maxJobs = $this->normalizeMaxJobs($value);
        continue;
      }

      if ($arg === '--max-jobs') {
        $value = $args[$index + 1] ?? null;
        if ($value !== null) {
          $maxJobs = $this->normalizeMaxJobs($value);
        }
      }
    }

    return $maxJobs;
  }

  private function normalizeMaxJobs(?string $value): int {
    if ($value === null || $value === '') {
      return 1;
    }

    $numeric = (int) $value;
    return $numeric > 0 ? $numeric : 1;
  }
}
