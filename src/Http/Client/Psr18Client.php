<?php
namespace Bamboo\Http\Client;

use Psr\Http\Client\ClientInterface as Psr18;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

final class Psr18Client implements ClientInterface {
  public function __construct(
    private Psr18 $client,
    private array $options = []
  ) {}

  public function send(RequestInterface $request): ResponseInterface {
    $req = $this->applyDefaults($request);
    return $this->withRetry(fn() => $this->client->sendRequest($req));
  }

  public function sendConcurrent(array $requests): array {
    $responses = [];
    $wg = new \OpenSwoole\Coroutine\WaitGroup();
    for ($i=0; $i<count($requests); $i++) {
      $wg->add();
      $r = $requests[$i];
      \OpenSwoole\Coroutine::create(function() use (&$responses, $i, $r, $wg) {
        try {
          $responses[$i] = $this->send($r);
        } catch (\Throwable $e) {
          $responses[$i] = (new Psr17Factory())->createResponse(599)->withBody(
            (new Psr17Factory())->createStream(json_encode(['error'=>$e->getMessage()]))
          );
        } finally { $wg->done(); }
      });
    }
    $wg->wait();
    ksort($responses);
    return $responses;
  }

  private function applyDefaults(RequestInterface $r): RequestInterface {
    foreach (($this->options['headers'] ?? []) as $k => $v) {
      if (!$r->hasHeader($k)) $r = $r->withHeader($k, $v);
    }
    return $r;
  }

  private function withRetry(callable $fn) {
    $cfg = $this->options['retries'] ?? [];
    $max = (int)($cfg['max'] ?? 0);
    $base = (int)($cfg['base_delay_ms'] ?? 100);
    $retryOn = $cfg['status_codes'] ?? [429,500,502,503,504];

    $attempt = 0;
    while (true) {
      $attempt++;
      try {
        $resp = $fn();
        if (!in_array($resp->getStatusCode(), $retryOn, true) || $attempt > $max + 1) {
          return $resp;
        }
      } catch (\Throwable $e) {
        if ($attempt > $max + 1) throw $e;
      }
      $delay = ($base * (2 ** ($attempt - 1))) / 1000;
      \OpenSwoole\Coroutine::sleep($delay);
    }
  }
}
