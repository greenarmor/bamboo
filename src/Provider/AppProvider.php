<?php
namespace Bamboo\Provider;
use Bamboo\Core\Application;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

class AppProvider {
  public function register(Application $app): void {
    $app->singleton('log', function() use ($app){
      $log = new \Monolog\Logger('bamboo');
      $log->pushHandler(new StreamHandler($app->config('app.log_file'), Level::Debug));
      return $log;
    });
    // HTTP Client facade
    $app->singleton(\Bamboo\Http\Client::class, fn() => new \Bamboo\Http\Client($app));
    $app->bind('http.client', fn() => $app->get(\Bamboo\Http\Client::class));
  }
}
