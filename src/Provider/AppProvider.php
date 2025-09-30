<?php
namespace Bamboo\Provider;
use Bamboo\Core\Application;
use Bamboo\Web\ProblemDetailsHandler;
use Bamboo\Web\RequestContextScope;
use Bamboo\Web\View\Engine\TemplateEngineManager;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

class AppProvider {
  public function register(Application $app): void {
    $app->singleton('log', function() use ($app){
      $log = new \Monolog\Logger('bamboo');
      $log->pushHandler(new StreamHandler($app->config('app.log_file'), Level::Debug));
      $log->pushProcessor(function(LogRecord $record) use ($app) {
        if (!$app->has(RequestContextScope::class)) {
          return $record;
        }
        $scope = $app->get(RequestContextScope::class);
        $context = $scope->get();
        if (!$context) {
          return $record;
        }
        $values = $context->all();
        if ($values === []) {
          return $record;
        }
        $extra = $record->extra;
        $extra['request'] = array_merge($extra['request'] ?? [], $values);
        return $record->with(extra: $extra);
      });
      return $log;
    });
    $app->singleton('redis.client.factory', function() use ($app) {
      return function(array $overrides = []) use ($app) {
        $config = array_replace($app->config('redis') ?? [], $overrides);
        $url = $config['url'] ?? 'tcp://127.0.0.1:6379';
        $options = $config['options'] ?? [];
        return new \Predis\Client($url, $options);
      };
    });
    $app->singleton(TemplateEngineManager::class, fn() => new TemplateEngineManager($app));
    $app->bind('view.engine', fn() => $app->get(TemplateEngineManager::class));
    // HTTP Client facade
    $app->singleton(\Bamboo\Http\Client::class, fn() => new \Bamboo\Http\Client($app));
    $app->bind('http.client', fn() => $app->get(\Bamboo\Http\Client::class));
    $app->singleton(ProblemDetailsHandler::class, fn() => new ProblemDetailsHandler($app->config('app.debug')));
    $app->bind('problem.handler', fn() => $app->get(ProblemDetailsHandler::class));
  }
}
