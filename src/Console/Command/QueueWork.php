<?php
namespace Bamboo\Console\Command;
class QueueWork extends Command {
  public function name(): string { return 'queue.work'; }
  public function description(): string { return 'Start a Redis-backed worker (BLPOP)'; }
  public function handle(array $args): int { 
$cfg = $this->app->config('redis');
$queue = $cfg['queue'];
$r = new Predis\Client($cfg['url']);
echo "Worker listening on '{$queue}'\n";
while (true) { $item = $r->blpop([$queue], 30); if (!$item) continue; [, $payload] = $item; echo "Job: {$payload}\n"; }
return 0; }
}
