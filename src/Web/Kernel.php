<?php
namespace Bamboo\Web;

use Bamboo\Core\Config;

class Kernel {
  protected array $config = [
    'global' => [],
    'groups' => [],
    'aliases' => [],
  ];

  protected array $expandedGlobal = [];
  protected array $resolved = [];
  protected string $configHash = '';
  protected ?string $routeCacheFile = null;
  protected ?int $routeCacheMtime = null;

  public function __construct(protected Config $configStore) {}

  public function forRoute(string $signature, array $routeMiddleware = []): array {
    $this->refreshConfiguration();

    if (!array_key_exists($signature, $this->resolved)) {
      $stack = array_merge(
        $this->expandedGlobal,
        $this->expandEntries($routeMiddleware)
      );

      $this->resolved[$signature] = array_values($stack);
    }

    return $this->resolved[$signature];
  }

  protected function refreshConfiguration(): void {
    $middleware = $this->configStore->get('middleware') ?? [];
    $hash = md5(serialize($middleware));
    $routeCacheFile = $this->configStore->get('cache.routes');
    $routeCacheMtime = ($routeCacheFile && file_exists($routeCacheFile)) ? filemtime($routeCacheFile) : null;

    if ($hash !== $this->configHash) {
      $this->config = $this->normalizeConfiguration($middleware);
      $this->expandedGlobal = $this->expandEntries($this->config['global']);
      $this->configHash = $hash;
      $this->resolved = [];
    }

    if ($routeCacheFile !== $this->routeCacheFile || $routeCacheMtime !== $this->routeCacheMtime) {
      $this->routeCacheFile = $routeCacheFile;
      $this->routeCacheMtime = $routeCacheMtime;
      $this->resolved = [];
    }
  }

  protected function normalizeConfiguration(array $config): array {
    $global = array_values(array_filter(
      array_map(static fn($value) => is_string($value) ? $value : (string) $value, $config['global'] ?? []),
      static fn($value) => $value !== ''
    ));

    $groups = [];
    foreach ($config['groups'] ?? [] as $name => $entries) {
      $groups[$name] = $this->normalizeMiddlewareList($entries);
    }

    $aliases = [];
    foreach ($config['aliases'] ?? [] as $alias => $target) {
      if (!is_string($alias)) continue;
      if ($target === null) continue;
      $aliases[$alias] = is_string($target) ? $target : (string) $target;
    }

    return [
      'global' => $global,
      'groups' => $groups,
      'aliases' => $aliases,
    ];
  }

  protected function expandEntries(array $entries, array $seenGroups = []): array {
    $resolved = [];
    foreach ($this->normalizeMiddlewareList($entries) as $entry) {
      foreach ($this->expandEntry($entry, $seenGroups) as $value) {
        $resolved[] = $value;
      }
    }
    return $resolved;
  }

  protected function expandEntry(mixed $entry, array $seenGroups): array {
    if (is_string($entry)) {
      if (isset($this->config['groups'][$entry])) {
        if (in_array($entry, $seenGroups, true)) {
          throw new \InvalidArgumentException(sprintf('Circular middleware group reference detected for "%s"', $entry));
        }

        return $this->expandEntries($this->config['groups'][$entry], [...$seenGroups, $entry]);
      }

      if (isset($this->config['aliases'][$entry])) {
        return [$this->config['aliases'][$entry]];
      }
    }

    return [$entry];
  }

  protected function normalizeMiddlewareList(mixed $middleware): array {
    if ($middleware === null) return [];
    if ($middleware instanceof \Traversable) {
      $middleware = iterator_to_array($middleware);
    }
    if (!is_array($middleware)) {
      return $middleware === '' ? [] : [(string) $middleware];
    }

    return array_values(array_filter(
      array_map(static fn($value) => is_string($value) ? $value : (string) $value, $middleware),
      static fn($value) => $value !== ''
    ));
  }
}
