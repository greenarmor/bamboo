<?php
namespace Bamboo\Web\Controller;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Bamboo\Core\Application;
use Bamboo\Web\View\LandingPageContent;
use Bamboo\Web\View\LandingShellRenderer;
class Home {
  public function __construct(protected Application $app) {}

  public function index(Request $request): Response {
    $contentBuilder = new LandingPageContent($this->app);
    $payload = $contentBuilder->payload($this->resolveDescriptor($request));

    $title = $this->escape($payload['meta']['title'] ?? 'Bamboo | Modern PHP Microframework');
    $metaTags = $this->buildMetaTags($payload['meta'] ?? []);

    $loadingMessage = $this->escape(sprintf('Loading %s experience…', $this->app->config('app.name', 'Bamboo')));
    $errorHtml = '<div class="bamboo-error" role="alert">Unable to load the Bamboo welcome experience. Refresh to try again.</div>';
    $encodedErrorHtml = json_encode($errorHtml, JSON_THROW_ON_ERROR);

    $renderer = new LandingShellRenderer();
    $html = $renderer->render([
      'title' => $title,
      'metaTags' => $metaTags,
      'loadingMessage' => $loadingMessage,
      'encodedErrorHtml' => $encodedErrorHtml,
    ]);

    return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
  }

  private function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  /**
   * @param array<string, mixed> $meta
   */
  private function buildMetaTags(array $meta): string {
    $tags = [];

    foreach ($meta as $name => $value) {
      if ($name === 'title') {
        continue;
      }

      if (!is_scalar($value) || $value === '') {
        continue;
      }

      $escapedName = $this->escape((string) $name);
      $escapedValue = $this->escape((string) $value);
      $tags[] = sprintf('    <meta name="%s" content="%s">', $escapedName, $escapedValue);
    }

    if ($tags === []) {
      return '';
    }

    return implode("\n", $tags) . "\n";
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
