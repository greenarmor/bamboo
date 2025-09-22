<?php
namespace Bamboo\Core;

use Closure;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Router {
  protected array $routes = [];

  public function addRoute(string $method, string $path, callable|array $action): void {
    $method = strtoupper($method);
    $this->routes[$method][$path] = $this->normalizeRoute($method, $path, $action);
  }

  public function get(string $path, callable|array $action) { $this->addRoute('GET', $path, $action); }
  public function post(string $path, callable|array $action) { $this->addRoute('POST', $path, $action); }
  public function all(): array { return $this->routes; }

  public function cacheTo(string $file): void {
    $closures = [];
    foreach ($this->routes as $method => $paths) {
      foreach ($paths as $path => $definition) {
        if ($this->containsClosure($definition['handler'])) {
          $closures[] = sprintf('%s %s', $method, $path);
        }
      }
    }
    if ($closures) {
      $list = implode(', ', $closures);
      throw new \RuntimeException("Cannot cache routes containing closures: {$list}");
    }

    $export = var_export($this->routes, true);
    $php = "<?php\nreturn {$export};\n";
    @mkdir(dirname($file), 0777, true); file_put_contents($file, $php);
  }

  private function containsClosure(mixed $handler): bool {
    if ($handler instanceof Closure) return true;
    if (is_array($handler)) {
      foreach ($handler as $value) {
        if ($this->containsClosure($value)) return true;
      }
    }
    return false;
  }

  public function match(Request $request): array {
    $dispatcher = simpleDispatcher(function(RouteCollector $r) {
      foreach ($this->routes as $method => $map) {
        foreach ($map as $path => $definition) {
          $r->addRoute($method, $path, $definition);
        }
      }
    });

    $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

    return match ($routeInfo[0]) {
      Dispatcher::NOT_FOUND => ['status' => Dispatcher::NOT_FOUND],
      Dispatcher::METHOD_NOT_ALLOWED => [
        'status' => Dispatcher::METHOD_NOT_ALLOWED,
        'allowed' => $routeInfo[1],
      ],
      Dispatcher::FOUND => [
        'status' => Dispatcher::FOUND,
        'route' => $routeInfo[1],
        'vars' => $routeInfo[2],
      ],
      default => ['status' => $routeInfo[0]],
    };
  }

  public function dispatch(Request $request, Application $app) {
    $match = $this->match($request);
    return $this->toResponse($match, $request, $app);
  }

  public function toResponse(array $match, Request $request, Application $app) {
    switch ($match['status']) {
      case Dispatcher::NOT_FOUND:
        return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Not Found']));
      case Dispatcher::METHOD_NOT_ALLOWED:
        return new Response(405, ['Content-Type' => 'application/json'], json_encode(['error' => 'Method Not Allowed']));
      case Dispatcher::FOUND:
        $definition = $match['route'];
        $handler = $definition['handler'];
        $vars = $match['vars'];
        if (is_array($handler) && !isset($handler['handler'])) {
          [$cls, $meth] = $handler;
          $ctrl = new $cls($app);
          return $ctrl->$meth($request, $vars);
        }
        $callable = $handler;
        if (is_array($handler) && isset($handler['handler'])) {
          $callable = $handler['handler'];
        }
        $arguments = $this->resolveCallableArguments($callable, $request, $vars, $app);
        return $callable(...$arguments);
    }

    return new Response(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Routing failure']));
  }

  private function normalizeRoute(string $method, string $path, callable|array $action): array {
    if (is_array($action) && array_key_exists('handler', $action) && array_key_exists('middleware', $action)) {
      return [
        'handler' => $action['handler'],
        'middleware' => $this->normalizeMiddleware($action['middleware']),
        'signature' => $action['signature'] ?? sprintf('%s %s', $method, $path),
      ];
    }

    $handler = $action;
    $middleware = [];

    if (is_array($action) && $this->isAssociative($action)) {
      $middleware = $this->normalizeMiddleware($action['middleware'] ?? []);
      $handler = $action['handler'] ?? $action['uses'] ?? $action['action'] ?? ($action[0] ?? null);
      if ($handler === null && isset($action[0], $action[1])) {
        $handler = [$action[0], $action[1]];
      }
      if ($handler === null) {
        throw new \InvalidArgumentException('Route definition is missing a handler.');
      }
    }

    return [
      'handler' => $handler,
      'middleware' => $this->normalizeMiddleware($middleware),
      'signature' => sprintf('%s %s', $method, $path),
    ];
  }

  private function normalizeMiddleware(mixed $middleware): array {
    if ($middleware === null) return [];
    if ($middleware instanceof \Traversable) {
      $middleware = iterator_to_array($middleware);
    }
    if (!is_array($middleware)) {
      return [(string) $middleware];
    }
    return array_values(array_map(static fn($item) => is_string($item) ? $item : (string) $item, $middleware));
  }

  private function isAssociative(array $array): bool {
    return array_keys($array) !== range(0, count($array) - 1);
  }

  private function resolveCallableArguments(callable $handler, Request $request, array $vars, Application $app): array {
    $reflection = $this->reflectCallable($handler);
    $available = [
      'request' => $request,
      'vars' => $vars,
      'app' => $app,
    ];
    $used = array_fill_keys(array_keys($available), false);
    $arguments = [];

    foreach ($reflection->getParameters() as $parameter) {
      $value = $this->matchParameter($parameter, $available, $used);
      if ($value === null) {
        if ($parameter->isOptional()) continue;
        $value = $this->nextAvailableDefault($available, $used);
        if ($value === null) continue;
      }
      $arguments[] = $value;
    }

    return $arguments;
  }

  private function reflectCallable(callable $handler): \ReflectionFunctionAbstract {
    if ($handler instanceof Closure) return new \ReflectionFunction($handler);
    if (is_array($handler)) return new \ReflectionMethod($handler[0], $handler[1]);
    if (is_string($handler) && str_contains($handler, '::')) return new \ReflectionMethod($handler);
    if (is_object($handler) && method_exists($handler, '__invoke')) return new \ReflectionMethod($handler, '__invoke');
    return new \ReflectionFunction($handler);
  }

  private function matchParameter(\ReflectionParameter $parameter, array $available, array &$used): mixed {
    $type = $parameter->getType();
    if ($type instanceof \ReflectionNamedType) {
      $typeName = $type->getName();
      if (!$type->isBuiltin()) {
        if (!$used['request'] && is_a($available['request'], $typeName)) {
          $used['request'] = true;
          return $available['request'];
        }
        if (!$used['app'] && is_a($available['app'], $typeName)) {
          $used['app'] = true;
          return $available['app'];
        }
      } elseif ($typeName === 'array' && !$used['vars']) {
        $used['vars'] = true;
        return $available['vars'];
      }
    }

    $name = strtolower($parameter->getName());
    $aliases = [
      'request' => ['request', 'req', 'serverrequest', 'serverrequestinterface'],
      'vars' => ['vars', 'args', 'arguments', 'params', 'parameters'],
      'app' => ['app', 'application'],
    ];

    foreach ($aliases as $key => $options) {
      if (in_array($name, $options, true) && !$used[$key]) {
        $used[$key] = true;
        return $available[$key];
      }
    }

    return null;
  }

  private function nextAvailableDefault(array $available, array &$used): mixed {
    foreach (['request', 'vars', 'app'] as $key) {
      if (!$used[$key]) {
        $used[$key] = true;
        return $available[$key];
      }
    }
    return null;
  }
}
