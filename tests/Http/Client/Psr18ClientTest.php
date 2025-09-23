<?php

namespace Tests\Http\Client;

use Bamboo\Http\Client\Psr18Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as Psr18;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Stubs\OpenSwooleHook;
use Tests\Support\OpenSwooleCompat;

class RecordingPsr18Client implements Psr18 {
  /** @var list<ResponseInterface|\Throwable> */
  private array $queue;

  /** @var list<RequestInterface> */
  public array $requests = [];

  /**
   * @param list<ResponseInterface|\Throwable> $queue
   */
  public function __construct(array $queue) {
    $this->queue = $queue;
  }

  public function sendRequest(RequestInterface $request): ResponseInterface {
    $this->requests[] = $request;
    if ($this->queue === []) {
      throw new \RuntimeException('No queued responses');
    }

    $next = $this->queue[0];
    if ($next instanceof ResponseInterface) {
      array_shift($this->queue);
      return $next;
    }

    if ($next instanceof \Throwable) {
      // keep the throwable queued so retries see the same exception
      if (count($this->queue) > 1) {
        $this->queue = array_merge([$next], array_slice($this->queue, 1));
      }
      throw $next;
    }

    array_shift($this->queue);
    throw new \RuntimeException('Unsupported queued value');
  }
}

class Psr18ClientTest extends TestCase {
  protected function tearDown(): void {
    Psr18Client::$forceWaitGroupAvailability = null;
    parent::tearDown();
  }

  public function testSendRetriesFailedResponses(): void {
    $psr17 = new Psr17Factory();
    $responses = [
      $psr17->createResponse(500)->withBody($psr17->createStream('fail')),
      $psr17->createResponse(200)->withBody($psr17->createStream('ok')),
    ];

    $delegate = new RecordingPsr18Client($responses);
    $client = new Psr18Client($delegate, [
      'headers' => ['User-Agent' => 'Test-Agent'],
      'retries' => ['max' => 1, 'status_codes' => [500]],
    ]);

    $request = $psr17->createRequest('GET', 'https://example.com');
    $response = $client->send($request);

      if (!$response instanceof ResponseInterface) {
        throw new \LogicException('Expected a response instance.');
      }

      /** @var ResponseInterface $response */
      $response = $response;

      $this->assertSame(200, $response->getStatusCode());
    $this->assertCount(2, $delegate->requests);
    $this->assertSame('Test-Agent', $delegate->requests[0]->getHeaderLine('User-Agent'));
    $this->assertSame('Test-Agent', $delegate->requests[1]->getHeaderLine('User-Agent'));
  }

  public function testSendConcurrentReturnsResponsesAndErrors(): void {
    $psr17 = new Psr17Factory();
    $responses = [
      $psr17->createResponse(200)->withBody($psr17->createStream('one')),
      new \RuntimeException('boom'),
    ];

    $delegate = new RecordingPsr18Client($responses);
    $client = new Psr18Client($delegate, []);

    $requests = [
      new ServerRequest('GET', 'https://example.com/a'),
      new ServerRequest('GET', 'https://example.com/b'),
    ];

    $results = $client->sendConcurrent($requests);

    $this->assertCount(2, $results);
    $this->assertSame('one', (string) $results[0]->getBody());
    $this->assertSame(200, $results[0]->getStatusCode());

    $this->assertSame(599, $results[1]->getStatusCode());
    $error = json_decode((string) $results[1]->getBody(), true);
    $this->assertSame('boom', $error['error']);
    $this->assertCount(3, $delegate->requests);
    $this->assertSame('https://example.com/a', (string) $delegate->requests[0]->getUri());
    $this->assertSame('https://example.com/b', (string) $delegate->requests[1]->getUri());
    $this->assertSame('https://example.com/b', (string) $delegate->requests[2]->getUri());
  }

  public function testSendConcurrentFallsBackWhenWaitGroupUnavailable(): void {
    $psr17 = new Psr17Factory();
    $responses = [
      $psr17->createResponse(200)->withBody($psr17->createStream('one')),
      new \RuntimeException('boom'),
    ];

    $delegate = new RecordingPsr18Client($responses);
    $client = new Psr18Client($delegate, []);

    Psr18Client::$forceWaitGroupAvailability = false;

    $requests = [
      new ServerRequest('GET', 'https://example.com/a'),
      new ServerRequest('GET', 'https://example.com/b'),
    ];

    $warnings = [];
    $results = [];
    set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
      $warnings[] = ['errno' => $errno, 'message' => $errstr];
      return true;
    });

    try {
      $results = $client->sendConcurrent($requests);
    } finally {
      restore_error_handler();
    }

    $this->assertCount(2, $results);
    $this->assertSame('one', (string) $results[0]->getBody());
    $this->assertSame(200, $results[0]->getStatusCode());

    $this->assertSame(599, $results[1]->getStatusCode());
    $error = json_decode((string) $results[1]->getBody(), true);
    $this->assertSame('boom', $error['error']);

    $this->assertCount(3, $delegate->requests);
    $this->assertSame('https://example.com/a', (string) $delegate->requests[0]->getUri());
    $this->assertSame('https://example.com/b', (string) $delegate->requests[1]->getUri());
    $this->assertSame('https://example.com/b', (string) $delegate->requests[2]->getUri());

    $this->assertCount(1, $warnings);
    $this->assertSame(E_USER_WARNING, $warnings[0]['errno']);
    $this->assertStringContainsString('WaitGroup not available', $warnings[0]['message']);
  }

  public function testSendConcurrentWithinOpenSwooleCoroutine(): void {
    if (OpenSwooleCompat::coroutineUsesStub()) {
      \OpenSwoole\Coroutine::$created = [];
    }

    $psr17 = new Psr17Factory();
    $responses = [
      $psr17->createResponse(200)->withBody($psr17->createStream('one')),
      $psr17->createResponse(200)->withBody($psr17->createStream('two')),
    ];

    $delegate = new RecordingPsr18Client($responses);
    $client = new Psr18Client($delegate, []);

    $requests = [
      new ServerRequest('GET', 'https://example.com/a'),
      new ServerRequest('GET', 'https://example.com/b'),
    ];

    $results = null;
    \OpenSwoole\Coroutine::run(function () use ($client, $requests, &$results): void {
      $results = $client->sendConcurrent($requests);
    });

    $this->assertIsArray($results);
    $this->assertCount(2, $results);
    $this->assertSame('one', (string) $results[0]->getBody());
    $this->assertSame('two', (string) $results[1]->getBody());
    if (OpenSwooleCompat::coroutineUsesStub()) {
      $this->assertCount(2, \OpenSwoole\Coroutine::$created);
    }
    $this->assertSame(-1, \OpenSwoole\Coroutine::getCid());
  }

  public function testSleepMicrosUsesCoroutineUsleepWhenAvailable(): void {
    if (!OpenSwooleCompat::coroutineUsesStub()) {
      $this->markTestSkipped('Coroutine stubs required to observe micro sleep usage.');
    }

    OpenSwooleHook::reset();

    $psr17 = new Psr17Factory();
    $responses = [
      $psr17->createResponse(500)->withBody($psr17->createStream('retry')),
      $psr17->createResponse(200)->withBody($psr17->createStream('ok')),
    ];

    $delegate = new RecordingPsr18Client($responses);
    $client = new Psr18Client($delegate, [
      'retries' => ['max' => 1, 'status_codes' => [500], 'base_delay_ms' => 1],
    ]);

    $request = $psr17->createRequest('GET', 'https://example.com/slow');

    $response = null;
    \OpenSwoole\Coroutine::run(function () use ($client, $request, &$response): void {
      $response = $client->send($request);
    });

    if (!$response instanceof ResponseInterface) {
      throw new \LogicException('Expected a response instance.');
    }

    /** @var ResponseInterface $response */
    $response = $response;

    $this->assertSame(200, $response->getStatusCode());
    $this->assertNotEmpty(OpenSwooleHook::$microSleeps);
    $this->assertSame([1000], OpenSwooleHook::$microSleeps);
    $this->assertSame(-1, \OpenSwoole\Coroutine::getCid());
  }
}
