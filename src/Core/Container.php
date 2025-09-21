<?php
namespace Bamboo\Core;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;

class Container implements ContainerInterface {
  protected array $bindings = [];
  protected array $instances = [];
  public function bind(string $id, callable $factory): void { $this->bindings[$id] = $factory; }
  public function singleton(string $id, callable $factory): void {
    $this->bindings[$id] = function($c) use ($factory, $id) {
      if (!isset($this->instances[$id])) $this->instances[$id] = $factory($c);
      return $this->instances[$id];
    };
  }
  public function get(string $id): mixed {
    if (isset($this->instances[$id])) return $this->instances[$id];
    if (!isset($this->bindings[$id])) throw new InvalidArgumentException("No entry for {$id}");
    return $this->bindings[$id]($this);
  }
  public function has(string $id): bool { return isset($this->instances[$id]) || isset($this->bindings[$id]); }
}
