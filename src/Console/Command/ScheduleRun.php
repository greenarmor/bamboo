<?php
namespace Bamboo\Console\Command;
class ScheduleRun extends Command {
  public function name(): string { return 'schedule.run'; }
  public function description(): string { return 'Run scheduled tasks (invoke from cron)'; }
  public function handle(array $args): int { 
echo "[".date('Y-m-d H:i:s')."] schedule.run tick\n"; return 0; }
}
