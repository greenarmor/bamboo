<?php
namespace Bamboo\Console\Command;
class DevWatch extends Command {
  public function name(): string { return 'dev.watch'; }
  public function description(): string { return 'Restart HTTP on file changes (requires inotifywait)'; }
  public function handle(array $args): int { 
$cmd = "inotifywait -q -m -e modify,create,delete src etc routes bootstrap public | while read; do pkill -f 'php bin/bamboo http.serve'; php bin/bamboo http.serve & done";
echo "Watching...\n"; passthru($cmd); return 0; }
}
