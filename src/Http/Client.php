<?php
namespace Bamboo\Http;

use Bamboo\Core\Application;
use Bamboo\Http\Client\Psr18Client;
use Http\Adapter\Guzzle7\Client as GuzzlePsr18;

class Client {
  public function __construct(private Application $app) {}

  public function for(?string $service = null): Psr18Client {
    $cfg = $this->app->config('http');
    $opts = $cfg['default'] ?? [];
    if ($service && isset($cfg['services'][$service])) {
      $opts = array_replace_recursive($opts, $cfg['services'][$service]);
    }

    $psr18 = new GuzzlePsr18(new \GuzzleHttp\Client([
      'timeout'  => $opts['timeout'] ?? 5.0,
      'base_uri' => $opts['base_uri'] ?? null,
      'http_errors' => false,
    ]));

    return new Psr18Client($psr18, $opts);
  }
}
