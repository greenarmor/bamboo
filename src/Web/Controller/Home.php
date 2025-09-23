<?php
namespace Bamboo\Web\Controller;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Bamboo\Core\Application;

class Home {
  public function __construct(protected Application $app) {}
  public function index(Request $request) {
    $data = [
      'framework' => $this->app->config('app.name'),
      'php' => PHP_VERSION,
      'swoole' => $this->resolveSwooleVersion(),
      'time' => date(DATE_ATOM)
    ];
    return new Response(200, ['Content-Type'=>'application/json'], json_encode($data));
  }

  private function resolveSwooleVersion(): string {
    if (defined('SWOOLE_VERSION')) {
      return SWOOLE_VERSION;
    }

    foreach (['openswoole', 'swoole'] as $extension) {
      if (extension_loaded($extension)) {
        $version = phpversion($extension);
        if (is_string($version) && $version !== '') {
          return $version;
        }
      }
    }

    return 'not installed';
  }
}
