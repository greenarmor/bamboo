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
    $payload = $contentBuilder->payload();

    $title = $this->escape($payload['meta']['title'] ?? 'Bamboo | Modern PHP Microframework');
    $description = $this->escape($payload['meta']['description'] ?? 'Bamboo makes high-performance PHP approachable.');

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
    <meta name="description" content="{$description}">
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

          if (payload && payload.meta && payload.meta.title) {
            document.title = payload.meta.title;
          }

          if (payload && payload.meta && payload.meta.description) {
            const descriptionTag = document.querySelector('meta[name="description"]');
            if (descriptionTag) {
              descriptionTag.setAttribute('content', payload.meta.description);
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
}
