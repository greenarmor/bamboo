<?php
namespace Bamboo\Web\Controller;

use Bamboo\Core\Application;
use Bamboo\Web\View\LandingPageContent;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LandingContentController {
  public function __construct(private Application $app) {}

  public function show(Request $request): Response {
    $builder = new LandingPageContent($this->app);
    $payload = $builder->payload($this->resolveDescriptor($request));

    return new Response(
      200,
      ['Content-Type' => 'application/json'],
      json_encode($payload, JSON_THROW_ON_ERROR)
    );
  }

  /**
   * @return array<string, scalar>
   */
  private function resolveDescriptor(Request $request): array {
    $descriptor = [];

    foreach ($request->getQueryParams() as $key => $value) {
      if (is_string($key) && is_scalar($value) && $value !== '') {
        $descriptor[$key] = is_string($value) ? $value : (string) $value;
      }
    }

    if (isset($descriptor['type']) && is_string($descriptor['type'])) {
      $descriptor['type'] = strtolower($descriptor['type']);
    }

    if ($descriptor !== []) {
      return $descriptor;
    }

    $configured = $this->app->config('landing.content');
    if (is_array($configured)) {
      $clean = [];
      foreach ($configured as $key => $value) {
        if (is_scalar($value) && $value !== '') {
          $clean[(string) $key] = (string) $value;
        }
      }

      if (isset($clean['type'])) {
        $clean['type'] = strtolower($clean['type']);
      }

      return $clean;
    }

    return [];
  }
}
