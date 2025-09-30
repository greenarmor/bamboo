<?php
namespace Bamboo\Web\Middleware;

use Bamboo\Web\RequestContextScope;
use Psr\Http\Message\ServerRequestInterface as Request;

class RequestId {
  public function __construct(private RequestContextScope $contextScope) {}
  public function handle(Request $request, \Closure $next) {
    $requestId = trim($request->getHeaderLine('X-Request-ID'));
    if ($requestId === '') { $requestId = $this->generateUuid(); }
    $context = $this->contextScope->getOrCreate();
    $context->merge([
      'id' => $requestId,
      'method' => $request->getMethod(),
      'route' => sprintf('%s %s', $request->getMethod(), $request->getRequestTarget()),
    ]);
    $request = $request
      ->withAttribute('request_id', $requestId)
      ->withAttribute('correlation_id', $requestId)
      ->withHeader('X-Request-ID', $requestId);
    $response = $next($request);
    return $response->withHeader('X-Request-ID', $requestId);
  }
  private function generateUuid(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
  }
}
