<?php
namespace Bamboo\Provider;
use Bamboo\Core\Application;
use Bamboo\Web\ProblemDetailsHandler;
use Bamboo\Web\RequestContext;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

class AppProvider {
  public function register(Application $app): void {
    $app->singleton('log', function() use ($app){
      $log = new \Monolog\Logger('bamboo');
      $log->pushHandler(new StreamHandler($app->config('app.log_file'), Level::Debug));
      $log->pushProcessor(function(LogRecord $record) use ($app) {
        if (!$app->has(RequestContext::class)) {
          return $record;
        }
        $context = $app->get(RequestContext::class)->all();
        if (!$context) {
          return $record;
        }
        $extra = $record->extra;
        $extra['request'] = array_merge($extra['request'] ?? [], $context);
        return $record->with(extra: $extra);
      });
      return $log;
    });
    // HTTP Client facade
    $app->singleton(\Bamboo\Http\Client::class, fn() => new \Bamboo\Http\Client($app));
    $app->bind('http.client', fn() => $app->get(\Bamboo\Http\Client::class));
    $app->singleton(ProblemDetailsHandler::class, fn() => new ProblemDetailsHandler($app->config('app.debug')));
    $app->bind('problem.handler', fn() => $app->get(ProblemDetailsHandler::class));
  }
}
