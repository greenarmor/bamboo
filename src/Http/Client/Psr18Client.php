<?php
namespace Bamboo\Http\Client;

use Psr\Http\Client\ClientInterface as Psr18;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

final class Psr18Client implements ClientInterface {
  /**
   * @internal allows tests to emulate WaitGroup availability
   */
  public static ?bool $forceWaitGroupAvailability = null;

  /**
   * @param array<string, mixed> $options
   */
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

    if (!$this->waitGroupAvailable()) {
      $this->logWaitGroupWarning();
      return $this->sendSequentially($requests);
    }

    return $this->sendWithWaitGroup($requests);
  }

  /**
   * @param list<RequestInterface> $requests
   * @return list<ResponseInterface>
   */
  private function sendSequentially(array $requests): array {
    $responses = [];
    foreach ($requests as $index => $request) {
      try {
        $responses[$index] = $this->send($request);
      } catch (\Throwable $e) {
        $responses[$index] = $this->createErrorResponse($e);
      }
    }

    ksort($responses);

    return array_values($responses);
  }

  /**
   * @param list<RequestInterface> $requests
   * @return list<ResponseInterface>
   */
  private function sendWithWaitGroup(array $requests): array {
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
            $responses[$index] = $this->createErrorResponse($e);
          } finally {
            $wg->done();
          }
        });
      }

      $wg->wait();
      ksort($responses);
    };

    if (method_exists(\OpenSwoole\Coroutine::class, 'getCid') && \OpenSwoole\Coroutine::getCid() >= 0) {
      $runner();
      return array_values($responses);
    }

    \OpenSwoole\Coroutine::run(function () use ($runner): void {
      $runner();
    });

    return array_values($responses);
  }

  private function waitGroupAvailable(): bool {
    if (self::$forceWaitGroupAvailability !== null) {
      return self::$forceWaitGroupAvailability;
    }

    return class_exists(\OpenSwoole\Coroutine\WaitGroup::class);
  }

  private function logWaitGroupWarning(): void {
    $message = 'OpenSwoole\\Coroutine\\WaitGroup not available; falling back to sequential HTTP requests. ' .
      'Verify that the OpenSwoole extension is installed and up to date to enable concurrent requests.';

    trigger_error($message, E_USER_WARNING);
    error_log($message);
  }

  private function createErrorResponse(\Throwable $e): ResponseInterface {
    $psr17 = new Psr17Factory();

    return $psr17->createResponse(599)->withBody(
      $psr17->createStream(json_encode(['error' => $e->getMessage()], JSON_THROW_ON_ERROR))
    );
  }

  private function applyDefaults(RequestInterface $r): RequestInterface {
    foreach (($this->options['headers'] ?? []) as $k => $v) {
      if (!$r->hasHeader($k)) $r = $r->withHeader($k, $v);
    }
    return $r;
  }

  /**
   * @param callable(): ResponseInterface $fn
   */
  private function withRetry(callable $fn): ResponseInterface {
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
