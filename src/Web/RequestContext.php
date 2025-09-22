<?php
namespace Bamboo\Web;

class RequestContext {
  private array $values = [];
  public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
  public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
  public function merge(array $values): void { foreach ($values as $key => $value) { $this->set($key, $value); } }
  public function all(): array { return $this->values; }
}
