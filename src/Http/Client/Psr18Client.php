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

  /**
   * @param list<RequestInterface> $requests
   * @return list<ResponseInterface>
   */
  public function sendConcurrent(array $requests): array {
    if ($requests === []) {
      return [];
    }

    /** @var array<int, ResponseInterface> $responses */
    $responses = [];
    $runner = function () use (&$responses, $requests): void {
      $wg = new \OpenSwoole\Coroutine\WaitGroup();
      foreach ($requests as $index => $request) {
        $wg->add();
        \OpenSwoole\Coroutine::create(function () use (&$responses, $index, $request, $wg) {
          try {
            $responses[$index] = $this->send($request);
          } catch (\Throwable $e) {
            $responses[$index] = (new Psr17Factory())->createResponse(599)->withBody(
              (new Psr17Factory())->createStream(json_encode(['error' => $e->getMessage()], JSON_THROW_ON_ERROR))
            );
          } finally {
            $wg->done();
          }
        });
      }
      $wg->wait();
      ksort($responses);
      $responses = array_values($responses);
    };

    if (method_exists(\OpenSwoole\Coroutine::class, 'getCid') && \OpenSwoole\Coroutine::getCid() >= 0) {
      $runner();
      return array_values($responses);
    }

    \OpenSwoole\Coroutine::run(function () use (&$responses, $runner): void {
      $runner();
    });

    return array_values($responses);
  }

  private function applyDefaults(RequestInterface $r): RequestInterface {
    foreach (($this->options['headers'] ?? []) as $k => $v) {
      if (!$r->hasHeader($k)) $r = $r->withHeader($k, $v);
    }
    return $r;
  }

  private function withRetry(callable $fn) {
    $cfg = $this->options['retries'] ?? [];
    $max = max(0, (int)($cfg['max'] ?? 0));
    $base = max(0, (int)($cfg['base_delay_ms'] ?? 100));
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
      $delayMicros = $this->backoffDelayMicros($base, $attempt);
      $this->sleepMicros($delayMicros);
    }
  }

  private function backoffDelayMicros(int $baseDelayMs, int $attempt): int {
    if ($baseDelayMs <= 0) {
      return 0;
    }

    $shift = max(0, $attempt - 1);
    $maxShift = (PHP_INT_SIZE * 8) - 2;
    if ($shift > $maxShift) {
      $shift = $maxShift;
    }

    $multiplier = 1 << $shift;
    if ($multiplier <= 0) {
      return PHP_INT_MAX;
    }

    $maxDelayMs = intdiv(PHP_INT_MAX, 1000);
    if ($baseDelayMs > intdiv($maxDelayMs, $multiplier)) {
      return $maxDelayMs * 1000;
    }

    $delayMs = $baseDelayMs * $multiplier;
    if ($delayMs > $maxDelayMs) {
      $delayMs = $maxDelayMs;
    }

    return $delayMs * 1000;
  }

  private function sleepMicros(int $delayMicros): void {
    if ($delayMicros <= 0) {
      return;
    }

    if (method_exists(\OpenSwoole\Coroutine::class, 'getCid') && \OpenSwoole\Coroutine::getCid() >= 0) {
      \OpenSwoole\Coroutine::usleep($delayMicros);
      return;
    }

    usleep($delayMicros);
  }
}
