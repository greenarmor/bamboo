<?php
namespace Bamboo\Core;

class Config {
  public function __construct(protected string $dir) {}

  public function all(): array {
    return [
      'app'     => require $this->dir . '/app.php',
      'server'  => require $this->dir . '/server.php',
      'cache'   => require $this->dir . '/cache.php',
      'redis'   => require $this->dir . '/redis.php',
      'database'=> file_exists($this->dir . '/database.php') ? require $this->dir . '/database.php' : [],
      'ws'      => require $this->dir . '/ws.php',
      'http'    => require $this->dir . '/http.php',
      'middleware' => file_exists($this->dir . '/middleware.php') ? require $this->dir . '/middleware.php' : [],
    ];
  }

  public function get(?string $key = null, $default = null) {
    $items = $this->all();
    if (!$key) return $items;
    $segments = explode('.', $key);
    $value = $items;
    foreach ($segments as $s) {
      $value = $value[$s] ?? null;
      if ($value === null) return $default;
    }
    return $value;
  }
}
