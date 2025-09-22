<?php

namespace Tests\Http;

use Bamboo\Web\ProblemDetailsHandler;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProblemDetailsHandlerTest extends TestCase {
  public function testCreatesProblemDetailsResponse(): void {
    $handler = new ProblemDetailsHandler(false);
    $request = new ServerRequest('GET', '/oops?debug=false');
    $throwable = new RuntimeException('Boom');
    $response = $handler->handle($throwable, $request);
    $this->assertSame(500, $response->getStatusCode());
    $this->assertSame(['application/problem+json'], $response->getHeader('Content-Type'));
    $data = json_decode((string)$response->getBody(), true);
    $this->assertIsArray($data);
    $this->assertSame('about:blank', $data['type']);
    $this->assertSame('Internal Server Error', $data['title']);
    $this->assertSame(500, $data['status']);
    $this->assertSame('Boom', $data['detail']);
    $this->assertSame('/oops?debug=false', $data['instance']);
    $this->assertArrayNotHasKey('debug', $data);
  }

  public function testDebugModeAddsDiagnostics(): void {
    $handler = new ProblemDetailsHandler(true);
    $request = new ServerRequest('GET', '/oops');
    $throwable = new RuntimeException('Exploded');
    $response = $handler->handle($throwable, $request);
    $data = json_decode((string)$response->getBody(), true);
    $this->assertIsArray($data);
    $this->assertArrayHasKey('debug', $data);
    $this->assertSame(RuntimeException::class, $data['debug']['exception']);
    $this->assertSame('Exploded', $data['debug']['message']);
    $this->assertIsArray($data['debug']['trace']);
    $this->assertNotEmpty($data['debug']['trace']);
  }

  public function testCorrelationIdIsIncludedWhenAvailable(): void {
    $handler = new ProblemDetailsHandler(false);
    $request = (new ServerRequest('GET', '/oops'))->withAttribute('correlation_id', 'abc-123');
    $throwable = new RuntimeException('fail');
    $response = $handler->handle($throwable, $request);
    $data = json_decode((string)$response->getBody(), true);
    $this->assertIsArray($data);
    $this->assertSame('abc-123', $data['correlationId']);
  }

  public function testHandlesInvalidUtf8InExceptionMessage(): void {
    $handler = new ProblemDetailsHandler(false);
    $invalidMessage = "Bad byte \xB1";
    $throwable = new RuntimeException($invalidMessage);
    $response = $handler->handle($throwable, new ServerRequest('GET', '/oops'));

    $payload = (string)$response->getBody();
    $data = json_decode($payload, true);

    $this->assertSame(JSON_ERROR_NONE, json_last_error());
    $this->assertIsArray($data);
    $this->assertSame('An unexpected error occurred.', $data['detail']);
    $this->assertSame(500, $data['status']);
  }
}
