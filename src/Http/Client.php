<?php
namespace Bamboo\Http;

use Bamboo\Core\Application;
use Bamboo\Http\Client\Psr18Client;
use Http\Adapter\Guzzle7\Client as GuzzlePsr18;

class Client {
  /**
   * @param callable(array<string, mixed>): \Psr\Http\Client\ClientInterface|null $psr18Factory
   */
  public function __construct(private Application $app, private $psr18Factory = null) {}

  public function for(?string $service = null): Psr18Client {
    $cfg = $this->app->config('http');
    $opts = $cfg['default'] ?? [];
    if ($service && isset($cfg['services'][$service])) {
      $opts = array_replace_recursive($opts, $cfg['services'][$service]);
    }

    $guzzleConfig = [
      'timeout'  => $opts['timeout'] ?? 5.0,
      'base_uri' => $opts['base_uri'] ?? null,
      'http_errors' => false,
    ];

    $factory = $this->psr18Factory ?? static fn(array $config): GuzzlePsr18 => new GuzzlePsr18(new \GuzzleHttp\Client($config));
    $psr18 = $factory($guzzleConfig);

    return new Psr18Client($psr18, $opts);
  }
}
