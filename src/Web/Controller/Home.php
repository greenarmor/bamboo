<?php
namespace Bamboo\Web\Controller;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Bamboo\Core\Application;

class Home {
  public function __construct(protected Application $app) {}

  public function index(Request $request): Response {
    $framework = $this->escape((string)$this->app->config('app.name', 'Bamboo'));
    $environment = $this->escape((string)$this->app->config('app.env', 'local'));
    $phpVersion = $this->escape(PHP_VERSION);
    $swooleVersion = $this->escape($this->resolveSwooleVersion());
    $currentTime = $this->escape(date('F j, Y g:i A T'));
    $docsUrl = $this->escape('https://github.com/greenarmor/bamboo');
    $startersUrl = $this->escape('https://github.com/greenarmor/bamboo/tree/main/docs/starters');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>{$framework} | Modern PHP Microframework</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      :root {
        color-scheme: dark;
        font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        line-height: 1.5;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        min-height: 100vh;
        background: radial-gradient(120% 120% at 50% 0%, rgba(94, 234, 212, 0.18) 0%, rgba(15, 23, 42, 1) 58%), #0f172a;
        color: #e2e8f0;
      }

      a {
        color: inherit;
      }

      .page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 96px 24px 48px;
        display: flex;
        flex-direction: column;
        gap: 64px;
      }

      .hero {
        display: grid;
        gap: 24px;
        text-align: center;
        justify-items: center;
      }

      .hero h1 {
        margin: 0;
        font-size: clamp(2.5rem, 6vw, 3.5rem);
        font-weight: 700;
        letter-spacing: -0.03em;
      }

      .hero p {
        margin: 0;
        max-width: 720px;
        color: #cbd5f5;
        font-size: 1.1rem;
      }

      .hero-badges {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px;
        font-size: 0.85rem;
        color: #94a3b8;
      }

      .pill {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 8px 18px;
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.35);
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(6px);
        letter-spacing: 0.08em;
      }

      .pill strong {
        color: #38bdf8;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.14em;
      }

      .pill .label {
        color: #cbd5f5;
        font-weight: 500;
        letter-spacing: 0.05em;
      }

      .hero-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 14px;
      }

      .cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 14px 28px;
        border-radius: 999px;
        font-weight: 600;
        text-decoration: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
      }

      .cta.primary {
        background: linear-gradient(135deg, #38bdf8, #10b981);
        color: #0f172a;
        box-shadow: 0 12px 32px rgba(56, 189, 248, 0.35);
      }

      .cta.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 40px rgba(16, 185, 129, 0.45);
      }

      .cta.secondary {
        background: rgba(148, 163, 184, 0.18);
        border: 1px solid rgba(148, 163, 184, 0.35);
        color: #e2e8f0;
      }

      .cta.secondary:hover {
        background: rgba(148, 163, 184, 0.35);
        transform: translateY(-1px);
      }

      .grid {
        display: grid;
        gap: 24px;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      }

      .card {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(148, 163, 184, 0.25);
        border-radius: 22px;
        padding: 26px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        box-shadow: 0 20px 42px rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(6px);
      }

      .card span.icon {
        display: inline-flex;
        width: 44px;
        height: 44px;
        border-radius: 14px;
        align-items: center;
        justify-content: center;
        background: rgba(56, 189, 248, 0.18);
        color: #38bdf8;
        font-size: 1.2rem;
      }

      .card h3 {
        margin: 0;
        font-size: 1.25rem;
        color: #f8fafc;
      }

      .card p {
        margin: 0;
        color: #cbd5f5;
        font-size: 0.98rem;
      }

      .stats {
        display: grid;
        gap: 18px;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      }

      .stat {
        background: rgba(15, 23, 42, 0.68);
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 20px;
        padding: 18px 22px;
      }

      .stat dt {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
      }

      .stat dd {
        margin: 10px 0 0;
        font-size: 1.35rem;
        font-weight: 600;
        color: #f8fafc;
      }

      .snippet {
        position: relative;
        background: rgba(15, 23, 42, 0.78);
        border-radius: 26px;
        padding: 32px;
        border: 1px solid rgba(56, 189, 248, 0.22);
        box-shadow: 0 24px 48px rgba(15, 23, 42, 0.5);
        overflow: hidden;
      }

      .snippet::before {
        content: "";
        position: absolute;
        inset: -40% 45% 20% -20%;
        background: radial-gradient(circle, rgba(14, 165, 233, 0.6), transparent 60%);
        opacity: 0.8;
        transform: rotate(18deg);
        pointer-events: none;
      }

      .snippet pre {
        margin: 0;
        font-family: 'Fira Code', 'JetBrains Mono', 'SFMono-Regular', Menlo, monospace;
        font-size: 0.95rem;
        color: #a5f3fc;
        position: relative;
        z-index: 1;
        white-space: pre-wrap;
      }

      footer {
        margin-top: auto;
        text-align: center;
        font-size: 0.85rem;
        color: #64748b;
      }

      footer a {
        color: #38bdf8;
        text-decoration: none;
      }

      footer a:hover {
        text-decoration: underline;
      }

      @media (max-width: 640px) {
        .page {
          padding: 72px 18px 36px;
        }

        .hero h1 {
          font-size: clamp(2.25rem, 9vw, 3rem);
        }

        .snippet {
          padding: 26px;
        }
      }
    </style>
  </head>
  <body>
    <div class="page">
      <section class="hero">
        <div class="hero-badges">
          <span class="pill"><strong>async</strong><span class="label">Powered by OpenSwoole</span></span>
          <span class="pill"><strong>{$environment}</strong><span class="label">Environment ready</span></span>
        </div>
        <h1>{$framework} makes high-performance PHP approachable.</h1>
        <p>Ship modern services with an event-driven HTTP kernel, first-class observability, and a developer experience inspired by Next.js — all without leaving PHP.</p>
        <div class="hero-actions">
          <a class="cta primary" href="{$docsUrl}" target="_blank" rel="noreferrer">Read the documentation</a>
          <a class="cta secondary" href="{$startersUrl}" target="_blank" rel="noreferrer">Explore starters</a>
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
          <dd>{$currentTime}</dd>
        </dl>
      </section>

      <section class="snippet" aria-label="Quick start commands">
        <pre>
$ composer create-project greenarmor/bamboo example-app
$ cd example-app
$ php bin/bamboo http.serve
        </pre>
      </section>

      <footer>
        Crafted with ❤️ for asynchronous PHP. Contribute on <a href="{$docsUrl}" target="_blank" rel="noreferrer">GitHub</a>.
      </footer>
    </div>
  </body>
</html>
HTML;

    return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
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
