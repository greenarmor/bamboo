<?php
namespace Bamboo\Web\View;

use Bamboo\Core\Application;
use Bamboo\Web\View\Engine\TemplateEngineManager;

class LandingPageContent {
  public function __construct(private Application $app) {}

  /**
   * Build the landing page payload consumed by both the API and the shell page.
   *
   * @return array{
   *   html: string,
   *   template: array{version:int,component:string,children:list<array<string,mixed>>},
   *   meta: array{title:string,description:string,generated_at:string}
   * }
   */
  public function payload(): array {
    $frameworkRaw = (string) $this->app->config('app.name', 'Bamboo');
    $environmentRaw = (string) $this->app->config('app.env', 'local');
    $phpVersionRaw = PHP_VERSION;
    $swooleVersionRaw = $this->resolveSwooleVersion();
    $generatedAtRaw = date('F j, Y g:i A T');

    $docsUrl = 'https://github.com/greenarmor/bamboo';
    $startersUrl = 'https://github.com/greenarmor/bamboo/tree/main/docs/starters';

    $template = [
      'version' => 1,
      'component' => 'page',
      'children' => [
        [
          'component' => 'hero',
          'title' => sprintf('%s makes high-performance PHP approachable.', $frameworkRaw !== '' ? $frameworkRaw : 'Bamboo'),
          'description' => 'Ship modern services with an event-driven HTTP kernel, first-class observability, and a developer experience inspired by Next.js — all without leaving PHP.',
          'badges' => [
            ['highlight' => 'async', 'label' => 'Powered by OpenSwoole'],
            ['highlight' => $environmentRaw !== '' ? $environmentRaw : 'local', 'label' => 'Environment ready'],
          ],
          'actions' => [
            [
              'label' => 'Read the documentation',
              'href' => $docsUrl,
              'variant' => 'primary',
              'external' => true,
            ],
            [
              'label' => 'Explore starters',
              'href' => $startersUrl,
              'variant' => 'secondary',
              'external' => true,
            ],
          ],
        ],
        [
          'component' => 'feature-grid',
          'ariaLabel' => 'Framework capabilities',
          'items' => [
            [
              'icon' => '⚡',
              'title' => 'Reactive HTTP core',
              'body' => 'Serve concurrent requests over OpenSwoole with routing, middleware pipelines, and graceful terminators that keep deployments predictable.',
            ],
            [
              'icon' => '🛠️',
              'title' => 'Composable modules',
              'body' => 'Bring queues, metrics, and WebSockets online with lightweight modules that register services, middleware, and console commands automatically.',
            ],
            [
              'icon' => '📈',
              'title' => 'Production observability',
              'body' => 'Expose Prometheus metrics, structured logs, and health probes out of the box so every service is ready for real workloads.',
            ],
            [
              'icon' => '⚙️',
              'title' => 'Developer velocity',
              'body' => 'Hot reload with dev.watch, test routes quickly, and lean on typed configuration to keep feedback loops fast.',
            ],
          ],
        ],
        [
          'component' => 'stat-grid',
          'ariaLabel' => 'Runtime details',
          'items' => [
            ['label' => 'PHP', 'value' => $phpVersionRaw],
            ['label' => 'OpenSwoole', 'value' => $swooleVersionRaw],
            ['label' => 'Environment', 'value' => $environmentRaw !== '' ? $environmentRaw : 'local'],
            ['label' => 'Generated', 'value' => $generatedAtRaw],
          ],
        ],
        [
          'component' => 'faq',
          'heading' => 'Frequently asked questions',
          'items' => [
            [
              'question' => 'How is Bamboo different from using OpenSwoole alone?',
              'answer' => 'Bamboo layers a ready-to-ship microframework on top of OpenSwoole with a project structure, dependency injection container, configuration loader, and router so you start productive immediately instead of hand-wiring the runtime.',
            ],
            [
              'question' => 'Why choose Bamboo over Laravel, Node.js, or other API frameworks?',
              'answer' => 'Bamboo pairs PHP familiarity with an async engine, built-in observability, and an operations-focused CLI, delivering the speed of evented runtimes while keeping the maintainability of typed PHP services.',
            ],
          ],
        ],
        [
          'component' => 'code-snippet',
          'ariaLabel' => 'Quick start commands',
          'lines' => [
            '$ composer create-project greenarmor/bamboo example-app',
            '$ cd example-app',
            '$ php bin/bamboo http.serve',
          ],
        ],
        [
          'component' => 'footer',
          'content' => [
            ['type' => 'text', 'value' => 'Crafted with ❤️ for asynchronous PHP. Contribute on '],
            ['type' => 'link', 'label' => 'GitHub', 'href' => $docsUrl, 'external' => true],
            ['type' => 'text', 'value' => '.'],
          ],
        ],
      ],
    ];

    $engineName = $this->resolveLandingEngine();
    $html = $this->app->get(TemplateEngineManager::class)->render($template, ['page' => 'landing'], $engineName);

    $metaTitle = sprintf('%s | Modern PHP Microframework', $frameworkRaw !== '' ? $frameworkRaw : 'Bamboo');
    $metaDescription = 'Bamboo makes high-performance PHP approachable.';

    return [
      'html' => $html,
      'template' => $template,
      'meta' => [
        'title' => $metaTitle,
        'description' => $metaDescription,
        'generated_at' => $generatedAtRaw,
      ],
    ];
  }

  private function resolveLandingEngine(): ?string {
    $configured = $this->app->config('view.pages.landing');
    if (is_string($configured) && $configured !== '') {
      return $configured;
    }

    return null;
  }

  private function resolveSwooleVersion(): string {
    if (defined('SWOOLE_VERSION')) {
      return SWOOLE_VERSION;
    }

    foreach (['openswoole', 'swoole'] as $extension) {
      if (extension_loaded($extension)) {
        $version = phpversion($extension);
        if (is_string($version) && $version !== '') {
          return $version;
        }
      }
    }

    return 'not installed';
  }
}
