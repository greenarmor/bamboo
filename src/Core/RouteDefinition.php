<?php
namespace Bamboo\Core;

class RouteDefinition {
  public function __construct(
    public readonly mixed $handler,
    public readonly mixed $middleware = [],
    public readonly mixed $middlewareGroups = [],
    public readonly ?string $signature = null,
  ) {}

  public static function forHandler(
    mixed $handler,
    mixed $middleware = [],
    mixed $middlewareGroups = [],
    ?string $signature = null,
  ): self {
    return new self($handler, $middleware, $middlewareGroups, $signature);
  }
}
