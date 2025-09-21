# BAMBOO (Bootstrapable Application Microframework Built for OpenSwoole Operations)

Distinct CLI, clean footprint (`etc/`, `var/`), and a **Client API layer** (PSR-18 HTTP client with retries + concurrency).

## Quick start
```bash
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

## CLI
http.serve, routes.show, routes.cache, cache.purge, app.key.make,
queue.work, ws.serve, dev.watch, schedule.run, pkg.info, client.call

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

Operational helpers cover Redis queue workers, WebSocket echo servers, an
inotify-based dev watcher, a scheduler tick for cron, Composer package
introspection, and PSR-18 client smoke tests.

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
tools include the `dev.watch` inotify loop and a timestamped scheduler
tick.

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

* Preserve the dot-notation CLI identity—no Artisan-style names or
  Laravel-like folder structures.
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
