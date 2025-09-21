<?php
namespace Bamboo\Core;

use Psr\Http\Message\ServerRequestInterface as Request;

class Application extends Container {
  public function __construct(protected Config $config) {
    $this->singleton(Config::class, fn() => $config);
    $this->bind('router', fn() => new Router());
    $this->bootRoutes();
  }
  public function config(?string $key = null, $default = null) { return $this->get(Config::class)->get($key, $default); }
  protected function bootRoutes(): void {
    $router = $this->get('router');
    $cache = $this->config('cache.routes');
    if ($cache && file_exists($cache)) {
      $map = require $cache;
      foreach ($map as $method => $paths) {
        foreach ($paths as $path => $handler) { $router->{strtolower($method)}($path, $handler); }
      }
      return;
    }
    require dirname(__DIR__,2).'/routes/http.php';
  }
  public function handle(Request $request) { return $this->get('router')->dispatch($request, $this); }
  public function register($provider): void { $provider->register($this); }
  public function bootEloquent(): void {
    $db = $this->config('database');
    if (!$db || empty($db['connections'])) return;
    $capsule = new \Illuminate\Database\Capsule\Manager();
    $capsule->addConnection($db['connections'][$db['default']] ?? []);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
  }
}
