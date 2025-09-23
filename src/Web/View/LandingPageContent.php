<?php
namespace Bamboo\Web\View;

use Bamboo\Core\Application;

class LandingPageContent {
  public function __construct(private Application $app) {}

  /**
   * Build the landing page payload consumed by both the API and the shell page.
   *
   * @return array{html:string,meta:array{title:string,description:string,generated_at:string}}
   */
  public function payload(): array {
    $frameworkRaw = (string) $this->app->config('app.name', 'Bamboo');
    $environmentRaw = (string) $this->app->config('app.env', 'local');
    $phpVersionRaw = PHP_VERSION;
    $swooleVersionRaw = $this->resolveSwooleVersion();
    $generatedAtRaw = date('F j, Y g:i A T');

    $docsUrl = 'https://github.com/greenarmor/bamboo';
    $startersUrl = 'https://github.com/greenarmor/bamboo/tree/main/docs/starters';

    $framework = $this->escape($frameworkRaw);
    $environment = $this->escape($environmentRaw);
    $phpVersion = $this->escape($phpVersionRaw);
    $swooleVersion = $this->escape($swooleVersionRaw);
    $generatedAt = $this->escape($generatedAtRaw);
    $docsHref = $this->escape($docsUrl);
    $startersHref = $this->escape($startersUrl);

    $html = <<<HTML
      <section class="hero">
        <div class="hero-badges">
          <span class="pill"><strong>async</strong><span class="label">Powered by OpenSwoole</span></span>
          <span class="pill"><strong>{$environment}</strong><span class="label">Environment ready</span></span>
        </div>
        <h1>{$framework} makes high-performance PHP approachable.</h1>
        <p>Ship modern services with an event-driven HTTP kernel, first-class observability, and a developer experience inspired by Next.js — all without leaving PHP.</p>
        <div class="hero-actions">
          <a class="cta primary" href="{$docsHref}" target="_blank" rel="noreferrer">Read the documentation</a>
          <a class="cta secondary" href="{$startersHref}" target="_blank" rel="noreferrer">Explore starters</a>
        </div>
      </section>

      <section class="grid" aria-label="Framework capabilities">
        <article class="card">
          <span class="icon">⚡</span>
          <h3>Reactive HTTP core</h3>
          <p>Serve concurrent requests over OpenSwoole with routing, middleware pipelines, and graceful terminators that keep deployments predictable.</p>
        </article>
        <article class="card">
          <span class="icon">🛠️</span>
          <h3>Composable modules</h3>
          <p>Bring queues, metrics, and WebSockets online with lightweight modules that register services, middleware, and console commands automatically.</p>
        </article>
        <article class="card">
          <span class="icon">📈</span>
          <h3>Production observability</h3>
          <p>Expose Prometheus metrics, structured logs, and health probes out of the box so every service is ready for real workloads.</p>
        </article>
        <article class="card">
          <span class="icon">⚙️</span>
          <h3>Developer velocity</h3>
          <p>Hot reload with <code>dev.watch</code>, test routes quickly, and lean on typed configuration to keep feedback loops fast.</p>
        </article>
      </section>

      <section class="stats" aria-label="Runtime details">
        <dl class="stat">
          <dt>PHP</dt>
          <dd>{$phpVersion}</dd>
        </dl>
        <dl class="stat">
          <dt>OpenSwoole</dt>
          <dd>{$swooleVersion}</dd>
        </dl>
        <dl class="stat">
          <dt>Environment</dt>
          <dd>{$environment}</dd>
        </dl>
        <dl class="stat">
          <dt>Generated</dt>
          <dd>{$generatedAt}</dd>
        </dl>
      </section>

      <section class="snippet" aria-label="Quick start commands">
        <pre>
$ composer create-project greenarmor/bamboo example-app
$ cd example-app
$ php bin/bamboo http.serve
        </pre>
      </section>

      <footer class="footer">
        Crafted with ❤️ for asynchronous PHP. Contribute on <a href="{$docsHref}" target="_blank" rel="noreferrer">GitHub</a>.
      </footer>
HTML;

    $metaTitle = sprintf('%s | Modern PHP Microframework', $frameworkRaw !== '' ? $frameworkRaw : 'Bamboo');
    $metaDescription = 'Bamboo makes high-performance PHP approachable.';

    return [
      'html' => $html,
      'meta' => [
        'title' => $metaTitle,
        'description' => $metaDescription,
        'generated_at' => $generatedAtRaw,
      ],
    ];
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
