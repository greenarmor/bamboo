<?php
namespace Bamboo\Core;

class Config {
  protected array $items = [];

  public function __construct(protected string $dir) {
    $this->items = $this->loadConfiguration();
  }

  public function all(): array {
    return $this->items;
  }

  public function get(?string $key = null, $default = null) {
    if ($key === null) return $this->items;
    $segments = explode('.', $key);
    $value = $this->items;
    foreach ($segments as $s) {
      if (!is_array($value) || !array_key_exists($s, $value)) return $default;
      $value = $value[$s];
    }
    return $value;
  }

  public function mergeMiddleware(array $contribution): void {
    if ($contribution === []) return;

    $middleware = $this->items['middleware'] ?? [];
    if (!is_array($middleware)) $middleware = [];

    $global = $this->normalizeMiddlewareList($middleware['global'] ?? []);
    if (array_key_exists('global', $contribution)) {
      $global = array_merge($global, $this->normalizeMiddlewareList($contribution['global']));
    }

    $groups = [];
    if (isset($middleware['groups']) && is_array($middleware['groups'])) {
      foreach ($middleware['groups'] as $name => $entries) {
        if (!is_string($name)) continue;
        $groups[$name] = $this->normalizeMiddlewareList($entries);
      }
    }

    if (isset($contribution['groups']) && is_array($contribution['groups'])) {
      foreach ($contribution['groups'] as $name => $entries) {
        if (!is_string($name)) continue;
        $existing = $groups[$name] ?? [];
        $groups[$name] = array_merge($existing, $this->normalizeMiddlewareList($entries));
      }
    }

    $aliases = [];
    if (isset($middleware['aliases']) && is_array($middleware['aliases'])) {
      foreach ($middleware['aliases'] as $alias => $target) {
        if (!is_string($alias)) continue;
        if (!is_string($target)) {
          $target = (string) $target;
        }
        if ($target === '') continue;
        $aliases[$alias] = $target;
      }
    }

    if (isset($contribution['aliases']) && is_array($contribution['aliases'])) {
      foreach ($contribution['aliases'] as $alias => $target) {
        if (!is_string($alias)) continue;
        if (!is_string($target)) {
          $target = (string) $target;
        }
        if ($target === '') continue;
        $aliases[$alias] = $target;
      }
    }

    $this->items['middleware'] = [
      'global' => array_values($global),
      'groups' => array_map(static fn(array $entries) => array_values($entries), $groups),
      'aliases' => $aliases,
    ];
  }

  protected function loadConfiguration(): array {
    return [
      'app'     => require $this->dir . '/app.php',
      'server'  => require $this->dir . '/server.php',
      'cache'   => require $this->dir . '/cache.php',
      'redis'   => require $this->dir . '/redis.php',
      'database'=> file_exists($this->dir . '/database.php') ? require $this->dir . '/database.php' : [],
      'ws'      => require $this->dir . '/ws.php',
      'http'    => require $this->dir . '/http.php',
      'middleware' => file_exists($this->dir . '/middleware.php') ? require $this->dir . '/middleware.php' : [],
      'metrics' => file_exists($this->dir . '/metrics.php') ? require $this->dir . '/metrics.php' : [
        'namespace' => 'bamboo',
        'storage' => [
          'driver' => 'in_memory',
        ],
        'histogram_buckets' => [
          'default' => [0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0],
        ],
      ],
      'resilience' => file_exists($this->dir . '/resilience.php') ? require $this->dir . '/resilience.php' : [
        'timeouts' => [
          'default' => 3.0,
          'per_route' => [],
        ],
        'circuit_breaker' => [
          'enabled' => true,
          'failure_threshold' => 5,
          'cooldown_seconds' => 30.0,
          'success_threshold' => 1,
          'per_route' => [],
        ],
        'health' => [
          'dependencies' => [],
        ],
      ],
    ];
  }

  protected function normalizeMiddlewareList(mixed $middleware): array {
    if ($middleware === null) return [];
    if ($middleware instanceof \Traversable) {
      $middleware = iterator_to_array($middleware);
    }
    if (!is_array($middleware)) {
      $value = (string) $middleware;
      return $value === '' ? [] : [$value];
    }

    $normalized = [];
    foreach ($middleware as $entry) {
      if ($entry === null) continue;
      if ($entry instanceof \Traversable) {
        foreach ($this->normalizeMiddlewareList(iterator_to_array($entry)) as $item) {
          $normalized[] = $item;
        }
        continue;
      }
      if (is_array($entry)) {
        foreach ($this->normalizeMiddlewareList($entry) as $item) {
          $normalized[] = $item;
        }
        continue;
      }

      $value = (string) $entry;
      if ($value === '') continue;
      $normalized[] = $value;
    }

    return array_values($normalized);
  }
}
