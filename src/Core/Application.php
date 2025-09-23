<?php
namespace Bamboo\Core;

use Bamboo\Module\ModuleInterface;
use Bamboo\Web\Kernel;
use Bamboo\Web\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class Application extends Container {
  /**
   * @var list<ModuleInterface>
   */
  protected array $modules = [];
  public function __construct(protected Config $config) {
    $this->singleton(Config::class, fn() => $config);
    $this->singleton('router', fn() => new Router());
    $this->singleton(Kernel::class, fn() => new Kernel($this->get(Config::class)));
    $this->bootRoutes();
  }
  /**
   * @param mixed $default
   * @return mixed
   */
  public function config(?string $key = null, $default = null) {
    return $this->get(Config::class)->get($key, $default);
  }
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
    require dirname(__DIR__, 2) . '/routes/http.php';
  }
  public function handle(Request $request): ResponseInterface {
    $context = new RequestContext();
    $context->merge([
      'method' => $request->getMethod(),
    ]);
    $this->instances[RequestContext::class] = $context;
    $this->instances['request.context'] = $context;
    $router = $this->get('router');
    $match = $router->match($request);
    $defaultSignature = sprintf('%s %s', $request->getMethod(), $request->getUri()->getPath());
    $context->merge(['route' => $defaultSignature]);

    $definition = $match['route'] ?? null;
    $routeMiddleware = [];
    $kernelCacheKey = $defaultSignature;

    if ($definition === null) {
      $kernelCacheKey = '__global__';
    } elseif (is_array($definition)) {
      $routeMiddleware = $router->gatherMiddleware($definition);
      $routeSignature = $definition['signature'] ?? $defaultSignature;
      $context->merge(['route' => $routeSignature]);
      $kernelCacheKey = $routeSignature;
    }

    $kernel = $this->get(Kernel::class);
    $middleware = $kernel->forRoute($kernelCacheKey, $routeMiddleware);
    $terminators = [];
    $runner = array_reduce(
      array_reverse($middleware),
      function(callable $next, string $middlewareClass) use (&$terminators) {
        return function(Request $request) use ($middlewareClass, $next, &$terminators) {
          $instance = $this->instantiateMiddleware($middlewareClass);
          if (!method_exists($instance, 'handle')) {
            throw new \BadMethodCallException(sprintf('Middleware %s must define a handle() method.', $middlewareClass));
          }
          $isTerminable = is_callable([$instance, 'terminate']);
          $requestForTerminate = $request;
          $response = $instance->handle($request, function(Request $nextRequest) use ($next, $isTerminable, &$requestForTerminate) {
            if ($isTerminable) {
              $requestForTerminate = $nextRequest;
            }
            return $next($nextRequest);
          });
          if ($isTerminable) {
            $terminators[] = [$instance, $requestForTerminate];
          }
          return $response;
        };
      },
      fn(Request $request) => $router->toResponse($match, $request, $this)
    );
    $response = $runner($request);
    foreach ($terminators as [$instance, $terminatorRequest]) {
      $instance->terminate($terminatorRequest, $response);
    }
    return $response;
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
  /**
   * @param object $provider
   */
  public function register(object $provider): void {
    if (!method_exists($provider, 'register')) {
      throw new \BadMethodCallException(sprintf('Service provider %s must define a register() method.', $provider::class));
    }

    $provider->register($this);
  }
  /**
   * @param list<class-string<ModuleInterface>> $moduleClasses
   */
  public function bootModules(array $moduleClasses): void {
    if ($moduleClasses === []) return;
    $config = $this->get(Config::class);
    /** @var list<ModuleInterface> $instances */
    $instances = [];
    foreach ($moduleClasses as $moduleClass) {
      if (!is_string($moduleClass) || $moduleClass === '') {
        throw new \InvalidArgumentException('Module class names must be non-empty strings.');
      }
      if (!class_exists($moduleClass)) {
        throw new \InvalidArgumentException(sprintf('Module class "%s" not found.', $moduleClass));
      }
      $module = new $moduleClass();
      if (!$module instanceof ModuleInterface) {
        throw new \InvalidArgumentException(sprintf('Module class "%s" must implement %s.', $moduleClass, ModuleInterface::class));
      }
      $module->register($this);
      $config->mergeMiddleware($module->middleware());
      $instances[] = $module;
    }
    foreach ($instances as $module) {
      $module->boot($this);
    }
    $this->modules = [...$this->modules, ...$instances];
  }
  public function bootEloquent(): void {
    $db = $this->config('database');
    if (!$db || empty($db['connections'])) return;
    $capsule = new \Illuminate\Database\Capsule\Manager();
    $capsule->addConnection($db['connections'][$db['default']] ?? []);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
  }
}
