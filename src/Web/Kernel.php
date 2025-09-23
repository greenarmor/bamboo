<?php
namespace Bamboo\Web;

use Bamboo\Core\Config;

/**
 * @phpstan-type MiddlewareConfig array{
 *   global: list<string>,
 *   groups: array<string, list<string>>,
 *   aliases: array<string, string>
 * }
 */
class Kernel {
  /**
   * @var MiddlewareConfig
   */
  protected array $config = [
    'global' => [],
    'groups' => [],
    'aliases' => [],
  ];

  /** @var list<string> */
  protected array $expandedGlobal = [];
  /** @var array<string, list<string>> */
  protected array $resolved = [];
  protected string $configHash = '';
  protected ?string $routeCacheFile = null;
  protected ?int $routeCacheMtime = null;

  public function __construct(protected Config $configStore) {}

  /**
   * @param list<string> $routeMiddleware
   * @return list<string>
   */
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
    $routeCacheFileRaw = $this->configStore->get('cache.routes');
    $routeCacheFile = is_string($routeCacheFileRaw) && $routeCacheFileRaw !== '' ? $routeCacheFileRaw : null;
    $routeCacheMtime = null;
    if ($routeCacheFile !== null && file_exists($routeCacheFile)) {
      $mtime = filemtime($routeCacheFile);
      $routeCacheMtime = $mtime === false ? null : $mtime;
    }

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

  /**
   * @param array{global?: iterable<mixed>, groups?: array<string, iterable<mixed>>, aliases?: array<string, mixed>} $config
   * @return MiddlewareConfig
   */
  protected function normalizeConfiguration(array $config): array {
    $globalSource = $config['global'] ?? [];
    if ($globalSource instanceof \Traversable) {
      $globalSource = iterator_to_array($globalSource);
    }
    if (!is_array($globalSource)) {
      $globalSource = [$globalSource];
    }

    $global = array_values(array_filter(
      array_map(static fn($value) => is_string($value) ? $value : (string) $value, $globalSource),
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

  /**
   * @param list<string> $entries
   * @param list<string> $seenGroups
   * @return list<string>
   */
  protected function expandEntries(array $entries, array $seenGroups = []): array {
    $resolved = [];
    foreach ($this->normalizeMiddlewareList($entries) as $entry) {
      foreach ($this->expandEntry($entry, $seenGroups) as $value) {
        $resolved[] = $value;
      }
    }
    return $resolved;
  }

  /**
   * @param list<string> $seenGroups
   * @return list<string>
   */
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

  /**
   * @return list<string>
   */
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
