<?php
namespace Bamboo\Core;

use Closure;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Router {
  protected array $routes = [];
  public function get(string $path, callable|array $action) { $this->routes['GET'][$path] = $action; }
  public function post(string $path, callable|array $action) { $this->routes['POST'][$path] = $action; }
  public function all(): array { return $this->routes; }
  public function cacheTo(string $file): void {
    $closures = [];
    foreach ($this->routes as $method => $paths) {
      foreach ($paths as $path => $handler) {
        if ($this->containsClosure($handler)) {
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
  public function dispatch(Request $request, Application $app) {
    $dispatcher = simpleDispatcher(function(RouteCollector $r){ foreach ($this->routes as $m=>$map){ foreach ($map as $p=>$h){ $r->addRoute($m, $p, $h); }}});
    $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());
    switch ($routeInfo[0]) {
      case \FastRoute\Dispatcher::NOT_FOUND: return new Response(404, ['Content-Type'=>'application/json'], json_encode(['error'=>'Not Found']));
      case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED: return new Response(405, ['Content-Type'=>'application/json'], json_encode(['error'=>'Method Not Allowed']));
      case \FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1]; $vars = $routeInfo[2];
        if (is_array($handler)) { [$cls,$meth] = $handler; $ctrl = new $cls($app); return $ctrl->$meth($request, $vars); }
        return $handler($request, $vars, $app);
    }
  }
}
