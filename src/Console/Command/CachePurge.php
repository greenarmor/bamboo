<?php
namespace Bamboo\Console\Command;
class CachePurge extends Command {
  public function name(): string { return 'cache.purge'; }
  public function description(): string { return 'Purge runtime cache files'; }
  public function handle(array $args): int { 
$dir = $this->app->config('cache.path');
if (!is_dir($dir)) { echo "No cache directory.\n"; return 0; }
foreach (glob($dir.'/*') as $f) @unlink($f);
echo "Cache purged.\n"; return 0; }
}
