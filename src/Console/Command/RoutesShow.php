<?php
namespace Bamboo\Console\Command;
class RoutesShow extends Command {
  public function name(): string { return 'routes.show'; }
  public function description(): string { return 'Display all registered routes'; }
  public function handle(array $args): int { 
$router = $this->app->get('router');
foreach ($router->all() as $method=>$map) {
  foreach ($map as $path=>$h) {
    $label = is_array($h) ? ($h[0].'@'.$h[1]) : 'Closure';
    printf("%-6s %-30s %s\n", $method, $path, $label);
  }
}
return 0; }
}
