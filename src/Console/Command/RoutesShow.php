<?php
namespace Bamboo\Console\Command;
class RoutesShow extends Command {
  public function name(): string { return 'routes.show'; }
  public function description(): string { return 'Display all registered routes'; }
  public function handle(array $args): int {
    $router = $this->app->get('router');
    foreach ($router->all() as $method => $map) {
      foreach ($map as $path => $definition) {
        $handler = $definition['handler'] ?? $definition;
        if (is_array($handler) && isset($handler['handler'])) {
          $handler = $handler['handler'];
        }
        $label = $this->formatHandler($handler);
        printf("%-6s %-30s %s\n", $method, $path, $label);
      }
    }
    return 0;
  }

  private function formatHandler(mixed $handler): string {
    if (is_array($handler)) {
      return sprintf('%s@%s', $handler[0], $handler[1]);
    }
    if (is_string($handler)) {
      return $handler;
    }
    return 'Closure';
  }
}
