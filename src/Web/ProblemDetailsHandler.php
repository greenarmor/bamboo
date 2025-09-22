<?php
namespace Bamboo\Web;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class ProblemDetailsHandler {
  public function __construct(private bool $debug) {}

  public function handle(Throwable $throwable, ?ServerRequestInterface $request = null): ResponseInterface {
    $status = $this->statusFrom($throwable);
    $problem = [
      'type' => $this->typeFor($status),
      'title' => $this->titleFor($status),
      'status' => $status,
      'detail' => $this->detailFrom($throwable),
      'instance' => $this->instanceFrom($request),
    ];

    if ($correlationId = $this->correlationIdFrom($request)) {
      $problem['correlationId'] = $correlationId;
    }

    if ($this->debug) {
      $problem['debug'] = [
        'exception' => get_class($throwable),
        'message' => $throwable->getMessage(),
        'trace' => explode("\n", $throwable->getTraceAsString()),
      ];
    }

    return new Response(
      $status,
      ['Content-Type' => 'application/problem+json'],
      json_encode($problem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
  }

  private function statusFrom(Throwable $throwable): int {
    if (method_exists($throwable, 'getStatusCode')) {
      $status = $throwable->getStatusCode();
      if (is_int($status) && $status >= 400 && $status <= 599) return $status;
    }
    $code = $throwable->getCode();
    if (is_int($code) && $code >= 400 && $code <= 599) return $code;
    return 500;
  }

  private function titleFor(int $status): string {
    try {
      return (new Response($status))->getReasonPhrase() ?: 'Error';
    } catch (\InvalidArgumentException $e) {
      return 'Error';
    }
  }

  private function typeFor(int $status): string {
    return 'about:blank';
  }

  private function detailFrom(Throwable $throwable): string {
    return $throwable->getMessage() ?: 'An unexpected error occurred.';
  }

  private function instanceFrom(?ServerRequestInterface $request): string {
    if (!$request) return 'about:blank';
    $target = $request->getRequestTarget();
    return $target === '' ? '/' : $target;
  }

  private function correlationIdFrom(?ServerRequestInterface $request): ?string {
    if (!$request) return null;
    $attribute = $request->getAttribute('correlation_id');
    if (is_string($attribute) && $attribute !== '') return $attribute;
    $header = $request->getHeaderLine('X-Correlation-Id');
    return $header !== '' ? $header : null;
  }
}
