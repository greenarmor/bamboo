<?php
namespace Bamboo\Web\Controller;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Bamboo\Core\Application;
use Bamboo\Web\View\LandingPageContent;
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

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
{$metaTags}
    <link rel="stylesheet" href="/assets/bamboo-ui.css">
  </head>
  <body>
    <div id="landing-root" class="bamboo-page">
      <div class="bamboo-loading" role="status" aria-live="polite">
        <span class="bamboo-spinner" aria-hidden="true"></span>
        <span class="label">{$loadingMessage}</span>
      </div>
    </div>
    <noscript>
      <div class="bamboo-page">
        <div class="bamboo-error" role="alert">Enable JavaScript to view the Bamboo landing experience.</div>
      </div>
    </noscript>
    <script type="module">
      import { renderTemplate } from '/assets/bamboo-ui.js';
      const root = document.getElementById('landing-root');

      async function renderLanding() {
        try {
          const response = await fetch('/api/landing', { headers: { 'Accept': 'application/json' } });
          if (!response.ok) {
            throw new Error('Failed with status ' + response.status);
          }

          const payload = await response.json();

          if (payload && payload.template) {
            renderTemplate(root, payload.template);
          } else if (payload && payload.html) {
            root.innerHTML = payload.html;
          }

          if (payload && payload.meta) {
            if (payload.meta.title) {
              document.title = payload.meta.title;
            }

            for (const [name, value] of Object.entries(payload.meta)) {
              if (!value || name === 'title') {
                continue;
              }

              const selector = 'meta[name="' + String(name).replace(/"/g, '\\"') + '"]';
              let tag = document.head.querySelector(selector);
              if (!tag) {
                tag = document.createElement('meta');
                tag.setAttribute('name', name);
                document.head.appendChild(tag);
              }

              tag.setAttribute('content', String(value));
            }
          }
        } catch (error) {
          root.innerHTML = {$encodedErrorHtml};
          console.error('Failed to load landing page payload', error);
        }
      }

      renderLanding();
    </script>
  </body>
</html>
HTML;

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
