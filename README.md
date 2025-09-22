# BAMBOO
**Bootstrapable Application Microservice, Built for OpenSwoole Operations**

A high-performance foundation for building PHP microservices on OpenSwoole.  
Built for distributed systems, async jobs, and scalable service-oriented apps.

## Philosophy

**Bamboo** is to PHP what **Express.js** is to Node.js:  
a simple, powerful, event-driven foundation for backend applications.

### Core Principles
1. **Stay Lightweight**  
   - Essentials only: HTTP server, routing, middleware, CLI.  
   - Add extras via Composer.

2. **Event-Driven PHP**  
   - Long-lived coroutine server with OpenSwoole.  
   - No PHP-FPM overhead.

3. **Express-Style DX**  
   - Clear routing & middleware.  
   - Dot-notation CLI (`php bin/bamboo http.serve`).

4. **Async by Default**  
   - Handle I/O without blocking.  
   - Task workers or Redis queues for heavier jobs.

5. **Composable, not Monolithic**  
   - No forced ORM or templating.  
   - Pick what *you* need: Eloquent, Doctrine, Twig, Redis, etc.

---

## Quick start
```bash
eval "$(./bootstrap/shell-init.sh)"  # ensure `composer` resolves to the local wrapper
composer install
cp .env.example .env
php bin/bamboo app.key.make
php bin/bamboo http.serve
```
Open: http://127.0.0.1:9501

## Installation on PHP 8.4

Need the full PHP 8.4 + OpenSwoole toolchain? Follow the [step-by-step installation and configuration guide](docs/Install-Bamboo-PHP84.md) for detailed package lists, environment setup, and service configuration tips.

## Try the client
```bash
php bin/bamboo client.call --url=https://httpbin.org/get
curl http://127.0.0.1:9501/api/httpbin
```

## Roadmap & configuration prep

The v0.3 planning notes live in
[`docs/roadmap/v0.3-prep.md`](docs/roadmap/v0.3-prep.md). The document outlines
the upcoming middleware pipeline, module contract, and where configuration will
reside. Placeholder configuration entry points
[`etc/middleware.php`](etc/middleware.php) and [`etc/modules.php`](etc/modules.php)
currently return empty arrays with documentation so contributors can stage their
changes without guessing future file names. The v0.4 observability plan is
captured in [`docs/roadmap/v0.4-prep.md`](docs/roadmap/v0.4-prep.md), covering the
`/metrics` exporter, Prometheus format contract, timeout/circuit-breaker
middleware, and graceful shutdown health hooks. The v1.0 API freeze and
documentation deliverables are tracked in
[`docs/roadmap/v1.0-prep.md`](docs/roadmap/v1.0-prep.md) so the community can
monitor checklist progress toward the stable milestone.

## CLI
http.serve, routes.show, routes.cache, cache.purge, app.key.make,
queue.work, ws.serve, dev.watch, schedule.run, pkg.info, client.call

## Quality tooling & CI

### Local developer workflow

Run `eval "$(./bootstrap/shell-init.sh)"` once per shell session to prepend the
repository's `bin/` directory to `PATH`. That ensures any `composer` invocation
uses the bundled wrapper, which filters PHP 8.4 deprecation noise until the
upstream Composer PHAR removes deprecated error-level constants. OpenSwoole must
be available locally, or append `--ignore-platform-req=ext-openswoole` for
read-only operations. Once dependencies are installed, use the provided
Composer scripts:

```bash
composer validate --strict   # verify composer.json/composer.lock structure
composer lint                # run PHP CS Fixer in dry-run mode
composer stan                # execute PHPStan (level 8, baseline enforced)
composer test                # run the full PHPUnit suite
```

The PHPStan baseline (`phpstan-baseline.neon`) captures existing
framework-specific dynamic behaviour. Refresh it after addressing reported
issues with `vendor/bin/phpstan analyse --generate-baseline` to keep the
baseline in sync.

### Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request across a PHP
8.2/8.3/8.4 matrix. Each job installs OpenSwoole, caches Composer and PHPUnit
artifacts, and then executes Composer validation, PHP CS Fixer (dry run),
PHPStan, and PHPUnit. On failure the workflow uploads collected logs and cache
artifacts for triage. Check the GitHub Actions tab for the latest status.

## Overview

Bamboo is a lean PHP application framework that runs on OpenSwoole and
keeps its identity anchored around a dot-notation CLI, a Composer-native
package story, and a deliberately small core. The default project layout
is intentionally minimal: configuration lives in `etc/`, runtime assets
in `var/`, source code in `src/`, routes in `routes/`, and the
`bin/bamboo` entry point provides the operational interface. The core
runtime boots instantly, surfaces helpful errors, and offers first-class
tooling for local development.

### Core runtime

The bootstrap sequence loads environment variables, assembles
configuration from the `etc/` directory, constructs the application
container, registers the default service provider, and boots Eloquent
when database connections are configured. `Bamboo\Core\Application`
extends the service container, shares the configuration instance,
instantiates the router, optionally loads cached routes, and exposes
helpers to dispatch PSR-7 requests or initialize Eloquent ORM support.

Configuration is centralized through `Bamboo\Core\Config`, which eagerly
loads the individual `etc/*.php` files and offers dot-notation lookups
for nested keys.

### HTTP serving model

The OpenSwoole HTTP server is configured from `etc/server.php`, including
worker counts, static file handling, and runtime limits, before wiring
request events to convert OpenSwoole requests into PSR-7 objects,
dispatch them through the application router, and emit PSR responses back
to the client with graceful error handling. A lightweight front
controller in `public/index.php` offers the same PSR pipeline when
executed under traditional PHP runtimes.

### HTTP surface

The router builds on FastRoute, supporting method-specific registration,
route caching to disk, and automatic resolution of controller classes or
closures when a match is found; it also returns JSON errors for
404/405 conditions. Default routes include a JSON landing page that
reports framework and runtime metadata, friendly greeting and echo
endpoints, an HTTP client demo that concurrently calls httpbin, and a
Redis-backed job enqueue endpoint, illustrating how modules plug into the
container and shared services.

### CLI & operations

The CLI boots via `bin/bamboo`, instantiates the console kernel, and
exposes a dot-notation command palette spanning HTTP operations, route
tooling, cache maintenance, key generation, queue and WebSocket workers,
developer hot-reload, scheduling, package introspection, and outbound
HTTP calls. Individual commands implement the expected behaviors, such as
starting the HTTP server, dumping or caching routes, purging runtime
caches, and generating a base64 application key if one is missing.

Operational helpers cover Redis queue workers, WebSocket echo servers, a
PHP-based dev watcher, a scheduler tick for cron, Composer package
introspection, and PSR-18 client smoke tests.

#### Development watcher

`dev.watch` now supervises `http.serve` through Symfony's Process
component while an event loop monitors project files. The watcher prefers
`ext-inotify` when available and falls back to a portable Finder-powered
polling driver. Each restart is logged through the shared Monolog
instance and the child process is signalled and awaited to keep OpenSwoole
shutdowns graceful.

The command accepts optional flags for custom workflows:

* `--debounce=<ms>` – Milliseconds to wait after the last detected change
  before restarting (defaults to 500ms).
* `--watch=<paths>` – Comma separated list of directories or files to
  monitor. The default covers `src/`, `etc/`, `routes/`, `bootstrap/`,
  and `public/`.
* `--command=<cmd>` – Override the supervised command. This is helpful
  when wiring in alternate HTTP stacks or scripted setups.
* `--` – Pass the remaining arguments verbatim as the command to run,
  which makes it easier to forward flags to the child process.

Example usage:

```
php bin/bamboo dev.watch --debounce=250 --watch=src,etc,routes --command="php bin/bamboo http.serve"
php bin/bamboo dev.watch -- php bin/bamboo http.serve --debug
```

### HTTP client & integration layer

`Bamboo\Http\Client` reads the HTTP configuration, merges per-service
overrides, and produces a PSR-18 client backed by Guzzle with sane
defaults for timeouts and HTTP error handling. The wrapped
`Psr18Client` applies default headers, retries based on configurable
status codes with exponential backoff, and can issue concurrent requests
using OpenSwoole coroutines, returning placeholder responses when
failures occur.

### Background & realtime capabilities

Redis connectivity is configured in `etc/redis.php`, enabling REST routes
to push jobs and the `queue.work` command to `BLPOP` and stream payloads
from the same queue. WebSocket support is provisioned through
`ws.serve`, which reads its host/port from `etc/ws.php` and spins up an
OpenSwoole echo server with lifecycle logging. Developer productivity
tools include the event-loop driven `dev.watch` command and a timestamped
scheduler tick.

### Configuration, logging & docs

App-level configuration exposes environment name, debug mode, and the
Monolog log destination, which the default provider uses to register a
shared logger alongside the HTTP client facade binding inside the
container. Server tuning parameters such as CPU-aware worker counts, task
workers, and static file handling live in `etc/server.php`, maintaining
the prescribed `etc/` hierarchy for runtime settings. The
`docs/OpenSwoole-Compat-and-Fixes.md` guide documents compatibility fixes
and setup steps required to run Bamboo v0.2 on PHP 8.4 with OpenSwoole
25.x rebuilt against the newer PHP runtime.

## Roadmap

The framework evolves along opinionated milestones that preserve
Bamboo's identity while incrementally expanding capabilities.

### Guiding principles

* Preserve the dot-notation CLI.
* Keep the core small and fast: HTTP server, router, dependency
  injection, configuration, and logging stay in the core; everything else
  ships as opt-in modules.
* Remain Composer-native with no custom package manager.
* Maintain clear boundaries with `etc/`, `routes/`, `src/`, `var/`, and
  `bin/bamboo`.
* Optimize developer experience with instant boot, helpful errors, and
  first-class local development tooling.

### Version milestones

* **v0.1 “Sprout” (shipped)** – Minimal HTTP server, router, middleware
  example, dot-notation CLI, route cache, app key generation, cache
  purge, Redis queue worker, WebSocket echo server, scheduler tick, and
  Guzzle demo route.
* **v0.2 “Stalk” (DX & stability)** – Error handler with
  `application/problem+json`, config validation on boot, structured logs
  with per-request correlation IDs, hot-reload improvements, and CI
  coverage (PHP 8.2/8.4, lint, type checks, tests).
* **v0.3 “Grove” (modules & extensibility)** – Middleware pipeline with
  groups, a simple job handler interface mapped from configuration, a
  `Bamboo\Module\ModuleInterface` for registering commands/routes/
  providers, and an optional HTTP client facade abstraction.
* **v0.4 “Canopy” (observability & resilience)** – Access log middleware,
  Prometheus text exporter, graceful shutdown hooks, worker health pings,
  request timeouts, and circuit breaker middleware.
* **v1.0 “Stand” (stable core)** – Frozen public APIs (CLI contract,
  router API, module API, config schema), upgrade guide, deprecation
  policy, benchmarks, and blueprints (API, WebSocket chat, job queue,
  cron).

### Task breakdown

* **v0.2** – Implement error handling, config validation, request ID
  middleware with structured logging, PHP-CS-Fixer + PHPStan tooling, and
  CI pipelines.
* **v0.3** – Deliver the middleware pipeline, job handler contract and
  worker dispatch, module API with `etc/modules.php`, and HTTP client
  facade.
* **v0.4** – Ship metrics counters/timers with a `/metrics` endpoint,
  timeout and circuit breaker middleware, health/readiness endpoints, and
  graceful shutdown hooks.
* **v1.0** – Freeze public contracts, publish an upgrade guide, and ship
  starter blueprints for common application types.

### Additional initiatives

* CLI command catalog spans introspection (`routes.show`, `pkg.info`),
  developer experience (`http.serve`, `dev.watch`), and operations
  (`routes.cache`, `cache.purge`, `schedule.run`, `queue.work`).
* Configuration conventions cover `etc/app.php`, `server.php`,
  `cache.php`, `redis.php`, `database.php`, `ws.php`, and forthcoming
  `middleware.php`, `queue.php`, and `modules.php`, with schema validation
  at boot.
* Testing and quality goals include Pest or PHPUnit suites, HTTP route
  smoke tests, unit tests for router/pipeline/CLI, PHPStan level 8,
  PHP-CS-Fixer rules, and optional pre-commit hooks.
* Packaging favors a lean core and optional Composer modules such as
  `bamboo/queue-redis`, `bamboo/metrics-prometheus`,
  `bamboo/validation`, and `bamboo/auth-jwt`, each exposing a module
  implementation.
* Security considerations include default headers middleware,
  rate-limiting, and input size/JSON parsing limits.
* Performance guidance targets benchmark scripts, documented
  OpenSwoole tuning, and deployment patterns (systemd/supervisord).
* Documentation priorities range from getting started, CLI usage,
  routing/middleware, queues/jobs, WebSockets, configuration, environment
  management, and deployment guides.
