<?php
namespace Bamboo\Console\Command;

use Nyholm\Psr7\Factory\Psr17Factory;

class ClientCall extends Command {
  public function name(): string { return 'client.call'; }
  public function description(): string { return 'Call a URL using the Bamboo HTTP client'; }
  public function usage(): string { return 'php bin/bamboo client.call --url=<https://example.com>'; }
  public function handle(array $args): int {
$psr17 = new Psr17Factory();
$url = null; foreach ($args as $a) if (str_starts_with($a, '--url=')) $url = substr($a, 6);
if (!$url) { echo 'Usage: ' . $this->usage() . "\n"; return 1; }
$http = $this->app->get('http.client');
$client = $http->for();
$req = $psr17->createRequest('GET', $url);
$res = $client->send($req);
echo "HTTP/{$res->getProtocolVersion()} {$res->getStatusCode()}\n";
echo (string)$res->getBody() . "\n"; return 0; }
}
