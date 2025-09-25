<?php
namespace Bamboo\Web\View;

use Bamboo\Core\Application;

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

    $html = $this->renderTemplate($template);

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

  /**
   * @param array{version?:int,component?:string,children?:array<int,array<string,mixed>>} $template
   */
  private function renderTemplate(array $template): string {
    if (($template['component'] ?? null) !== 'page') {
      return '';
    }

    $children = $template['children'] ?? [];
    if (!is_array($children)) {
      return '';
    }

    $segments = [];
    foreach ($children as $child) {
      if (!is_array($child)) {
        continue;
      }

      $segments[] = $this->renderComponent($child);
    }

    return implode("\n\n", array_filter($segments));
  }

  /**
   * @param array<string, mixed> $component
   */
  private function renderComponent(array $component): string {
    return match ($component['component'] ?? null) {
      'hero' => $this->renderHero($component),
      'feature-grid' => $this->renderFeatureGrid($component),
      'stat-grid' => $this->renderStatGrid($component),
      'faq' => $this->renderFaq($component),
      'code-snippet' => $this->renderSnippet($component),
      'footer' => $this->renderFooter($component),
      default => '',
    };
  }

  /**
   * @param array{
   *   component?:string,
   *   title?:string,
   *   description?:string,
   *   badges?:array<int,array{highlight?:string,label?:string}>,
   *   actions?:array<int,array{label?:string,href?:string,variant?:string,external?:bool}>
   * } $component
   */
  private function renderHero(array $component): string {
    $badgesHtml = $this->renderBadges($component['badges'] ?? []);
    $titleHtml = '';
    if (isset($component['title']) && $component['title'] !== '') {
      $titleHtml = '<h1>' . $this->escape((string) $component['title']) . '</h1>';
    }

    $descriptionHtml = '';
    if (isset($component['description']) && $component['description'] !== '') {
      $descriptionHtml = '<p>' . $this->escape((string) $component['description']) . '</p>';
    }

    $actionsHtml = $this->renderActions($component['actions'] ?? []);

    $content = array_filter([$badgesHtml, $titleHtml, $descriptionHtml, $actionsHtml]);
    if ($content === []) {
      return '';
    }

    return "<section class=\"bamboo-hero\">\n" . $this->indent(implode("\n\n", $content)) . "\n</section>";
  }

  /**
   * @param array<int, array{highlight?:string,label?:string}> $badges
   */
  private function renderBadges(array $badges): string {
    if ($badges === []) {
      return '';
    }

    $items = [];
    foreach ($badges as $badge) {
      if (!is_array($badge)) {
        continue;
      }

      $parts = [];
      if (isset($badge['highlight']) && $badge['highlight'] !== '') {
        $parts[] = '<strong>' . $this->escape((string) $badge['highlight']) . '</strong>';
      }

      if (isset($badge['label']) && $badge['label'] !== '') {
        $parts[] = '<span class="label">' . $this->escape((string) $badge['label']) . '</span>';
      }

      if ($parts === []) {
        continue;
      }

      $items[] = '<span class="bamboo-pill">' . implode('', $parts) . '</span>';
    }

    if ($items === []) {
      return '';
    }

    return '<div class="bamboo-hero-badges">' . implode('', $items) . '</div>';
  }

  /**
   * @param array<int, array{label?:string,href?:string,variant?:string,external?:bool}> $actions
   */
  private function renderActions(array $actions): string {
    if ($actions === []) {
      return '';
    }

    $links = [];
    foreach ($actions as $action) {
      if (!is_array($action)) {
        continue;
      }

      $label = isset($action['label']) ? trim((string) $action['label']) : '';
      if ($label === '') {
        continue;
      }

      $href = isset($action['href']) ? trim((string) $action['href']) : '#';
      $variantRaw = isset($action['variant']) && $action['variant'] !== ''
        ? preg_replace('/[^a-z0-9_-]/i', '', (string) $action['variant'])
        : 'secondary';
      $variant = ($variantRaw === null || $variantRaw === '') ? 'secondary' : $variantRaw;

      $attributes = sprintf(' class="bamboo-cta %s" href="%s"', $this->escape($variant), $this->escape($href));
      $external = !empty($action['external']);
      if ($external) {
        $attributes .= ' target="_blank" rel="noreferrer"';
      }

      $links[] = sprintf('<a%s>%s</a>', $attributes, $this->escape($label));
    }

    if ($links === []) {
      return '';
    }

    return '<div class="bamboo-hero-actions">' . implode('', $links) . '</div>';
  }

  /**
   * @param array{
   *   component?:string,
   *   ariaLabel?:string,
   *   items?:array<int,array{icon?:string,title?:string,body?:string}>
   * } $component
   */
  private function renderFeatureGrid(array $component): string {
    $items = $component['items'] ?? [];
    if (!is_array($items) || $items === []) {
      return '';
    }

    $cards = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }

      $parts = [];
      if (isset($item['icon']) && $item['icon'] !== '') {
        $parts[] = '<span class="icon">' . $this->escape((string) $item['icon']) . '</span>';
      }

      if (isset($item['title']) && $item['title'] !== '') {
        $parts[] = '<h3>' . $this->escape((string) $item['title']) . '</h3>';
      }

      if (isset($item['body']) && $item['body'] !== '') {
        $parts[] = '<p>' . $this->escape((string) $item['body']) . '</p>';
      }

      if ($parts === []) {
        continue;
      }

      $cards[] = '<article class="bamboo-card">' . implode('', $parts) . '</article>';
    }

    if ($cards === []) {
      return '';
    }

    $attributes = '';
    if (isset($component['ariaLabel']) && $component['ariaLabel'] !== '') {
      $attributes = ' aria-label="' . $this->escape((string) $component['ariaLabel']) . '"';
    }

    return sprintf('<section class="bamboo-grid"%s>%s</section>', $attributes, implode('', $cards));
  }

  /**
   * @param array{
   *   component?:string,
   *   ariaLabel?:string,
   *   items?:array<int,array{label?:string,value?:string}>
   * } $component
   */
  private function renderStatGrid(array $component): string {
    $items = $component['items'] ?? [];
    if (!is_array($items) || $items === []) {
      return '';
    }

    $stats = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }

      $label = isset($item['label']) ? $this->escape((string) $item['label']) : '';
      $value = isset($item['value']) ? $this->escape((string) $item['value']) : '';
      if ($label === '' && $value === '') {
        continue;
      }

      $stat = '<dl class="bamboo-stat">';
      if ($label !== '') {
        $stat .= '<dt>' . $label . '</dt>';
      }
      if ($value !== '') {
        $stat .= '<dd>' . $value . '</dd>';
      }
      $stat .= '</dl>';
      $stats[] = $stat;
    }

    if ($stats === []) {
      return '';
    }

    $attributes = '';
    if (isset($component['ariaLabel']) && $component['ariaLabel'] !== '') {
      $attributes = ' aria-label="' . $this->escape((string) $component['ariaLabel']) . '"';
    }

    return sprintf('<section class="bamboo-stats"%s>%s</section>', $attributes, implode('', $stats));
  }

  /**
   * @param array{
   *   component?:string,
   *   heading?:string,
   *   items?:array<int,array{question?:string,answer?:string}>
   * } $component
   */
  private function renderFaq(array $component): string {
    $items = $component['items'] ?? [];
    if (!is_array($items) || $items === []) {
      return '';
    }

    $entries = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }

      $question = isset($item['question']) ? $this->escape((string) $item['question']) : '';
      $answer = isset($item['answer']) ? $this->escape((string) $item['answer']) : '';
      if ($question === '' && $answer === '') {
        continue;
      }

      $entry = '<article class="bamboo-faq-item">';
      if ($question !== '') {
        $entry .= '<h3>' . $question . '</h3>';
      }
      if ($answer !== '') {
        $entry .= '<p>' . $answer . '</p>';
      }
      $entry .= '</article>';
      $entries[] = $entry;
    }

    if ($entries === []) {
      return '';
    }

    $heading = '';
    if (isset($component['heading']) && $component['heading'] !== '') {
      $heading = '<h2>' . $this->escape((string) $component['heading']) . '</h2>';
    }

    $content = $heading !== '' ? $heading . implode('', $entries) : implode('', $entries);

    return '<section class="bamboo-faq">' . $content . '</section>';
  }

  /**
   * @param array{
   *   component?:string,
   *   ariaLabel?:string,
   *   lines?:array<int,string>
   * } $component
   */
  private function renderSnippet(array $component): string {
    $lines = $component['lines'] ?? [];
    if (!is_array($lines) || $lines === []) {
      return '';
    }

    $escapedLines = array_map(function($line) {
      return $this->escape((string) $line);
    }, $lines);

    $pre = '<pre>' . implode("\n", $escapedLines) . '</pre>';

    $attributes = '';
    if (isset($component['ariaLabel']) && $component['ariaLabel'] !== '') {
      $attributes = ' aria-label="' . $this->escape((string) $component['ariaLabel']) . '"';
    }

    return sprintf('<section class="bamboo-snippet"%s>%s</section>', $attributes, $pre);
  }

  /**
   * @param array{
   *   component?:string,
   *   content?:array<int,array{type?:string,value?:string,label?:string,href?:string,external?:bool}>
   * } $component
   */
  private function renderFooter(array $component): string {
    $content = $component['content'] ?? [];
    if (!is_array($content) || $content === []) {
      return '';
    }

    $parts = [];
    foreach ($content as $piece) {
      if (!is_array($piece)) {
        continue;
      }

      if (($piece['type'] ?? '') === 'text' && isset($piece['value'])) {
        $parts[] = $this->escape((string) $piece['value']);
        continue;
      }

      if (($piece['type'] ?? '') === 'link' && isset($piece['label'])) {
        $label = trim((string) $piece['label']);
        if ($label === '') {
          continue;
        }

        $href = isset($piece['href']) ? (string) $piece['href'] : '#';
        $attributes = sprintf(' href="%s"', $this->escape($href));
        if (!empty($piece['external'])) {
          $attributes .= ' target="_blank" rel="noreferrer"';
        }

        $parts[] = sprintf('<a%s>%s</a>', $attributes, $this->escape($label));
      }
    }

    if ($parts === []) {
      return '';
    }

    return '<footer class="bamboo-footer">' . implode('', $parts) . '</footer>';
  }

  private function indent(string $html, int $level = 1): string {
    $indent = str_repeat('  ', $level);
    $lines = preg_split("/\r?\n/", $html) ?: [];

    $indented = array_map(function(string $line) use ($indent) {
      if ($line === '') {
        return '';
      }

      return $indent . $line;
    }, $lines);

    return implode("\n", $indented);
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

  private function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}
