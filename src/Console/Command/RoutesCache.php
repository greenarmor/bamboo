<?php
namespace Bamboo\Console\Command;
class RoutesCache extends Command {
  public function name(): string { return 'routes.cache'; }
  public function description(): string { return 'Cache routes to var/cache/routes.cache.php'; }
  public function handle(array $args): int { 
$file = $this->app->config('cache.routes');
$this->app->get('router')->cacheTo($file);
echo "Routes cached -> {$file}\n"; return 0; }
}
