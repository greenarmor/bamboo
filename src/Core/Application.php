<?php
namespace Bamboo\Core;

use Bamboo\Web\Kernel;
use Bamboo\Web\RequestContext;
use Psr\Http\Message\ServerRequestInterface as Request;

class Application extends Container {
  public function __construct(protected Config $config) {
    $this->singleton(Config::class, fn() => $config);
    $this->singleton('router', fn() => new Router());
    $this->singleton(Kernel::class, fn() => new Kernel($this->get(Config::class)));
    $this->bootRoutes();
  }
  public function config(?string $key = null, $default = null) { return $this->get(Config::class)->get($key, $default); }
  protected function bootRoutes(): void {
    $router = $this->get('router');
    $cache = $this->config('cache.routes');
    if ($cache && file_exists($cache)) {
      $map = require $cache;
      foreach ($map as $method => $paths) {
        foreach ($paths as $path => $definition) { $router->addRoute($method, $path, $definition); }
      }
      return;
    }
    require dirname(__DIR__,2).'/routes/http.php';
  }
  public function handle(Request $request) {
    $context = new RequestContext();
    $context->merge([
      'method' => $request->getMethod(),
    ]);
    $this->instances[RequestContext::class] = $context;
    $this->instances['request.context'] = $context;
    $router = $this->get('router');
    $match = $router->match($request);
    $definition = $match['route'] ?? null;
    $routeSignature = $definition['signature'] ?? sprintf('%s %s', $request->getMethod(), $request->getUri()->getPath());
    $routeMiddleware = $definition['middleware'] ?? [];
    $context->merge(['route' => $routeSignature]);
    $kernel = $this->get(Kernel::class);
    $middleware = $kernel->forRoute($routeSignature, $routeMiddleware);
    $runner = array_reduce(
      array_reverse($middleware),
      function(callable $next, string $middlewareClass) {
        return function(Request $request) use ($middlewareClass, $next) {
          $instance = $this->instantiateMiddleware($middlewareClass);
          return $instance->handle($request, $next);
        };
      },
      fn(Request $request) => $router->toResponse($match, $request, $this)
    );
    return $runner($request);
  }
  protected function instantiateMiddleware(string $middleware): object {
    if (!class_exists($middleware)) {
      throw new \InvalidArgumentException("Middleware {$middleware} not found");
    }
    $ref = new \ReflectionClass($middleware);
    $ctor = $ref->getConstructor();
    if (!$ctor || $ctor->getNumberOfRequiredParameters() === 0) {
      return new $middleware();
    }
    $args = [];
    foreach ($ctor->getParameters() as $param) {
      $type = $param->getType();
      if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
        $name = $type->getName();
        if (is_a($this, $name)) { $args[] = $this; continue; }
        if ($this->has($name)) { $args[] = $this->get($name); continue; }
      }
      if ($param->isDefaultValueAvailable()) { $args[] = $param->getDefaultValue(); continue; }
      throw new \RuntimeException("Unable to resolve dependency for {$middleware}::{$param->getName()}");
    }
    return $ref->newInstanceArgs($args);
  }
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
