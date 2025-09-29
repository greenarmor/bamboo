<?php
namespace Bamboo\Web\View;

use Bamboo\Core\Application;
use Bamboo\Web\View\Engine\TemplateEngineManager;

class LandingPageContent {
  public function __construct(private Application $app) {}

  /**
   * Build the landing page payload consumed by both the API and the shell page.
   *
   * @param array<string, scalar> $descriptor
   *
   * @return array{
   *   html: string,
   *   template: array{version:int,component:string,children:list<array<string,mixed>>},
   *   meta: array<string, string>
   * }
   */
  public function payload(array $descriptor = []): array {
    $frameworkRaw = (string) $this->app->config('app.name', 'Bamboo');
    $environmentRaw = (string) $this->app->config('app.env', 'local');
    $phpVersionRaw = PHP_VERSION;
    $swooleVersionRaw = $this->resolveSwooleVersion();
    $generatedAtRaw = date('F j, Y g:i A T');

    $docsUrl = 'https://greenarmor.github.io/bamboo/';
    $startersUrl = 'https://github.com/greenarmor/bamboo/tree/main/docs/starters';
    $fundingUrl = 'https://www.buymeacoffee.com/greenarmor';
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
            ['type' => 'text', 'value' => 'Support the project via '],
            ['type' => 'link', 'label' => 'Buy Me a Coffee', 'href' => $fundingUrl, 'external' => true],
            ['type' => 'text', 'value' => ' and help build Bamboo on '],
            ['type' => 'link', 'label' => 'GitHub', 'href' => $docsUrl, 'external' => true],
            ['type' => 'text', 'value' => '.'],
          ],
        ],
      ],
    ];

    $engineName = $this->resolveLandingEngine();
    $html = $this->app->get(TemplateEngineManager::class)->render($template, ['page' => 'landing'], $engineName);

    return [
      'html' => $html,
      'template' => $template,
      'meta' => $this->buildMeta($descriptor, $frameworkRaw, $generatedAtRaw),
    ];
  }

  /**
   * @param array<string, scalar> $descriptor
   *
   * @return array<string, string>
   */
  private function buildMeta(array $descriptor, string $frameworkRaw, string $generatedAtRaw): array {
    $framework = $frameworkRaw !== '' ? $frameworkRaw : 'Bamboo';
    $typeRaw = $descriptor['type'] ?? null;
    $type = is_string($typeRaw) && $typeRaw !== '' ? strtolower($typeRaw) : 'framework';

    $defaults = match ($type) {
      'article' => [
        'title' => sprintf('%s | Async PHP in Production', $framework),
        'description' => 'A behind-the-scenes look at shipping Bamboo services at scale.',
        'author' => 'Bamboo Editorial Team',
        'publication' => 'Green Armor Engineering',
      ],
      'about' => [
        'title' => sprintf('About %s', $framework),
        'description' => 'Learn about the mission driving Bamboo and the people behind it.',
        'mission' => 'Make high-performance PHP approachable for every team.',
        'team_lead' => 'Jordan Queue',
      ],
      'food' => [
        'title' => sprintf('%s Test Kitchen | Async Ramen', $framework),
        'description' => 'A comforting bowl that keeps OpenSwoole chefs happy.',
        'cuisine' => 'Fusion',
        'prep_time' => '45 minutes',
        'chef' => 'Chef Queue Worker',
      ],
      'book' => [
        'title' => sprintf('Scaling Services with %s', $framework),
        'description' => 'A handbook for building resilient PHP microservices on Bamboo.',
        'isbn' => '978-1-955555-01-2',
        'publisher' => 'Green Armor Press',
        'author' => 'Jordan Queue',
      ],
      default => [
        'title' => sprintf('%s | Modern PHP Microframework', $framework),
        'description' => 'Bamboo makes high-performance PHP approachable.',
      ],
    };

    $overrides = [];
    foreach ($descriptor as $key => $value) {
      if ($key === 'type') {
        continue;
      }

      if (is_scalar($value) && $value !== '') {
        $overrides[$key] = (string) $value;
      }
    }

    $meta = array_merge($defaults, $overrides);
    $meta['generated_at'] = $generatedAtRaw;

    foreach ($meta as $key => $value) {
      if (!is_scalar($value)) {
        unset($meta[$key]);
        continue;
      }

      $meta[$key] = (string) $value;
    }

    return $meta;
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
