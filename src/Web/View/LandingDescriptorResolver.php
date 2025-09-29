<?php
namespace Bamboo\Web\View;

use Bamboo\Core\Application;
use Psr\Http\Message\ServerRequestInterface as Request;

class LandingDescriptorResolver {
  public function __construct(private Application $app) {}

  /**
   * @return array<string, scalar>
   */
  public function resolve(Request $request): array {
    $descriptor = $this->normalizeQueryParameters($request->getQueryParams());

    if ($descriptor !== []) {
      return $descriptor;
    }

    $configured = $this->app->config('landing.content');
    if (is_array($configured)) {
      return $this->normalizeConfiguredDescriptor($configured);
    }

    return [];
  }

  /**
   * @param array<string, mixed> $params
   * @return array<string, scalar>
   */
  private function normalizeQueryParameters(array $params): array {
    $descriptor = [];

    foreach ($params as $key => $value) {
      if (!is_string($key)) {
        continue;
      }

      if (is_scalar($value) && $value !== '') {
        $descriptor[$key] = is_string($value) ? $value : (string) $value;
      }
    }

    if (isset($descriptor['type'])) {
      $descriptor['type'] = strtolower((string) $descriptor['type']);
    }

    return $descriptor;
  }

  /**
   * @param array<string|int, mixed> $configured
   * @return array<string, scalar>
   */
  private function normalizeConfiguredDescriptor(array $configured): array {
    $descriptor = [];

    foreach ($configured as $key => $value) {
      if (!is_scalar($value) || $value === '') {
        continue;
      }

      $descriptor[(string) $key] = is_string($value) ? $value : (string) $value;
    }

    if (isset($descriptor['type'])) {
      $descriptor['type'] = strtolower((string) $descriptor['type']);
    }

    return $descriptor;
  }
}
