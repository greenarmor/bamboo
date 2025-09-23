# Starter Blueprint Hub

This hub documents the officially supported Bamboo starter blueprints and how to
operate them in both development and production environments. Each starter is a
curated distribution of the framework with guardrails, sample code, and tooling
that accelerate focused workloads. Use the guidance below to scaffold a project,
configure runtime services, and wire the blueprint into your deployment
workflow.

## Using this guide

### Prerequisites
- **PHP 8.4 with OpenSwoole** – install the extensions documented in
  [`docs/Install-Bamboo-PHP84.md`](../Install-Bamboo-PHP84.md) before creating a
  project.
- **Composer** – run the repository's `bootstrap/shell-init.sh` script so the
  bundled Composer wrapper is available in your `PATH` and suppresses deprecated
  notices during installation.
- **Redis 7+** – required for the queue worker starter and optional for the REST
  API starter's job dispatch examples.
- **Node.js 20+** – only required when consuming the WebSocket starter's demo
  client or bundling TypeScript utilities. The PHP server runs without Node.js.

### Project bootstrap workflow
1. `eval "$(./bootstrap/shell-init.sh)"` (from the Bamboo repository or any
   starter checkout) to register local tooling shims.
2. Run the `composer create-project` command for the desired starter.
3. Copy the provided `.env.example` to `.env` and update credentials for Redis,
   databases, queues, and TLS endpoints.
4. Generate an application key with `php bin/bamboo app.key.make` when prompted.
5. Start the relevant runtime (`http.serve`, `queue.work`, or `ws.serve`) and
   run through the smoke tests documented below.

### Configuration conventions
- Starter configuration files live in `etc/` just like the main framework. Each
  blueprint ships tuned defaults in `etc/app.php`, `etc/server.php`,
  `etc/http.php`, and the service-specific files listed in the sections below.
- Environment variables override configuration values through `vlucas/phpdotenv`
  loading inside the bootstrap sequence. Use `.env` for secrets and per-machine
  overrides; commit baseline settings to version control under `etc/`.
- Routes, jobs, and WebSocket handlers are organised under `routes/`,
  `app/Jobs/`, and `app/WebSocket/` (or equivalent) to keep scaffolding familiar
  across blueprints.

---

## REST API starter

A batteries-included HTTP API surface with request validation, error handling,
OpenAPI generation, and first-party queue dispatch hooks.

### Create a project
```bash
eval "$(./bootstrap/shell-init.sh)"
composer create-project bamboo/starter-rest my-api
cd my-api
cp .env.example .env
php bin/bamboo app.key.make
```

### What ships in the template
- Route groups under `routes/api.php` with JSON and streaming responses.
- Controller skeletons in `app/Http/Controllers/` using PSR-7 requests and
  responses.
- Request validation middleware powered by `respect/validation` with sensible
  defaults for pagination, UUIDs, and ISO-8601 timestamps.
- Example feature tests in `tests/Feature/` asserting authentication guards,
  validation failures, and happy-path payloads.
- Optional Redis-backed job dispatchers demonstrating background email or audit
  pipelines.

### Configuration notes
- **HTTP server** – tune worker counts, coroutine hooks, and static file serving
  in `etc/server.php`. Production deployments should raise the worker count to
  match CPU cores and enable `open_http2_protocol` when terminating TLS upstream.
- **Application metadata** – set the app name, environment, and default locale in
  `etc/app.php`. The starter enables JSON pretty-printing in `etc/http.php` when
  `APP_DEBUG=true`.
- **Database connections** – configure `etc/database.php` with your primary
  connection. The template ships with SQLite enabled for local smoke tests;
  adjust the `DB_CONNECTION` and `DB_URL` environment variables for production.
- **API authentication** – the starter provides token guard scaffolding under
  `etc/auth.php` and middleware stubs in `app/Http/Middleware/Authenticate.php`.
  Register your preferred guard (JWT, OAuth, or signed tokens) before shipping.
- **Queues** – toggle queue dispatch by enabling the Redis connection in
  `etc/redis.php` and setting `QUEUE_CONNECTION=redis` in `.env`.

### Operational workflow
- Run `php bin/bamboo http.serve` to boot the OpenSwoole HTTP server (defaults to
  `127.0.0.1:9501`).
- Use `php bin/bamboo routes.show` to inspect registered endpoints and confirm
  that cacheable routes align with your OpenAPI spec.
- Prime route caches in CI/CD with `php bin/bamboo routes.cache` and purge caches
  via `php bin/bamboo cache.purge` after deployments.
- Execute the template's test suite using `composer test`; it covers controllers,
  middleware, and queue dispatch hooks.
- Generate and publish API documentation by running the included `npm run docs`
  task (if OpenAPI export is enabled) or integrating the generated spec into your
  chosen portal.

### Production checklist
- Front requests with an HTTP proxy (NGINX, Envoy, Traefik) that terminates TLS
  and forwards to OpenSwoole over a Unix socket or loopback address.
- Configure process supervision via systemd or Supervisor with automatic restarts
  on failure and graceful reloads on deploy.
- Enable structured logging in `etc/http.php` and forward JSON logs to your
  observability stack.
- Monitor latency and saturation using the built-in Prometheus metrics endpoint
  (mounted under `/metrics` when `etc/metrics.php['enabled']` is true).

---

## Queue worker starter

A Redis-backed background worker with opinionated job dispatch patterns,
structured logging, and health probes.

### Create a project
```bash
eval "$(./bootstrap/shell-init.sh)"
composer create-project bamboo/starter-queue my-worker
cd my-worker
cp .env.example .env
php bin/bamboo app.key.make
```

### What ships in the template
- Job contracts in `app/Jobs/` demonstrating synchronous and batched handlers.
- Redis queue dispatcher bound in the service container so controllers can call
  `queue()->push()` without additional wiring.
- Worker supervisor configuration samples for systemd, Supervisor, and Horizon.
- Health check routes that expose queue depth and worker heartbeat information.
- Integration tests in `tests/Queue/` verifying job payload serialization and
  Redis connectivity.

### Configuration notes
- **Redis connection** – set host, port, and authentication in `etc/redis.php`.
  The starter expects a dedicated queue database (default index `1`) so metrics
  and application caches remain isolated.
- **Queue channel** – adjust the queue name under `etc/queue.php['default']`. The
  worker command reads `redis.queue` from configuration, so keep names in sync.
- **Job retry policy** – customise retry delays and maximum attempts in
  `etc/queue.php['retry']`. The starter includes exponential backoff helpers you
  can reuse across job classes.
- **Telemetry** – enable structured logs by pointing `LOG_CHANNEL` to `stdout` in
  `.env` and wiring `etc/app.php['logging']` to the Monolog JSON formatter.

### Operational workflow
- Start a local Redis instance (`redis-server --save "" --appendonly no`) before
  launching workers.
- Run `php bin/bamboo queue.work` to start a long-lived worker. Use `--once` for
  smoke tests or `--max-jobs=500` during rolling deploys to ensure workers exit
  before process managers recycle them.
- Send test jobs via the bundled `php bin/bamboo client.call` commands or HTTP
  enqueue endpoints. Check the console output for payload traces while
  validating.
- Run `composer test` to execute integration tests that ensure Redis connectivity
  and job handlers function as expected.
- Configure health probes: the starter exposes `/ops/queue-health` for HTTP
  checks and writes a heartbeat timestamp to Redis. Point Kubernetes liveness and
  readiness probes at the HTTP endpoint or configure a CLI probe that evaluates
  the heartbeat key.

### Production checklist
- Run at least two worker processes per queue to avoid head-of-line blocking on
  long-running jobs. Scale horizontally by duplicating the systemd or Supervisor
  unit with different process names.
- Set `--max-jobs` or `--once` flags so rolling deploys drain existing work
  before shipping new code.
- Instrument queue depth with the provided Prometheus collector (`queue_depth`
  gauge) and export logs to your centralised logging provider.
- Protect Redis with TLS or private networking; if using managed Redis services,
  enable `REDIS_TLS=true` in `.env` and update `etc/redis.php` accordingly.

---

## WebSocket gateway starter

A real-time gateway that layers authentication, channel management, and
broadcast helpers on top of OpenSwoole's WebSocket server.

### Create a project
```bash
eval "$(./bootstrap/shell-init.sh)"
composer create-project bamboo/starter-websocket my-gateway
cd my-gateway
cp .env.example .env
php bin/bamboo app.key.make
```

### What ships in the template
- Gateway bootstrap in `app/WebSocket/Server.php` that wires authentication and
  channel registration callbacks into OpenSwoole.
- HTTP handshake routes in `routes/websocket.php` integrating with the REST API
  starter's auth middleware so clients can request signed connection tokens.
- Broadcast helpers in `app/WebSocket/Broadcaster.php` for fan-out to rooms and
  presence channels.
- A TypeScript demo client (under `resources/client/`) showcasing connection
  lifecycle, ping/pong handling, and fallback to HTTP polling.
- Metrics collectors reporting connected clients, message throughput, and error
  rates to the shared Prometheus exporter.

### Configuration notes
- **WebSocket server** – configure bind address, port, SSL certificates, and task
  worker counts in `etc/ws.php`. Production deployments typically listen on a
  Unix socket or private interface and sit behind a TLS-terminating proxy.
- **HTTP server bridge** – ensure `etc/server.php['dispatch_mode']` is set to
  `3` (queue) when co-hosting HTTP and WebSocket servers on the same instance so
  task workers are not starved.
- **Authentication** – update `etc/auth.php` to match the token issuing strategy
  used by your REST API. The starter provides hooks for signed JWTs and HMAC
  tokens. Rotate secrets through `.env`.
- **Broadcast driver** – configure Redis Pub/Sub under `etc/redis.php['pubsub']`
  so multiple gateway replicas stay in sync. Alternative drivers (Kafka, NATS)
  can be registered by binding a custom broadcaster into the service container.

### Operational workflow
- Start the gateway with `php bin/bamboo ws.serve`. The command reads host and
  port settings from `etc/ws.php` and logs connection lifecycle events.
- Optionally run the HTTP server simultaneously (`php bin/bamboo http.serve`) to
  serve REST endpoints and token negotiation routes. Bind to different ports or
  use Unix sockets when running both on one machine.
- Execute the bundled smoke test: `npm install && npm run dev:ws` in
  `resources/client/` to connect to the gateway, exchange messages, and exercise
  authentication flows.
- Use `composer test` to run the WebSocket-specific unit tests covering channel
  authorisation and broadcaster fan-out logic.
- Validate graceful shutdown by sending `SIGTERM` to the worker process and
  observing that the server stops accepting new connections while draining active
  clients.

### Production checklist
- Terminate TLS at a reverse proxy that supports WebSocket upgrades (NGINX,
  HAProxy, or Traefik) and forward connections to the OpenSwoole server over a
  persistent upstream.
- Enable Prometheus metrics by setting `METRICS_ENABLED=true` in `.env` and
  configuring scrape targets for the gateway instances.
- Scale horizontally by running multiple `ws.serve` processes behind the proxy
  and enabling Redis Pub/Sub broadcasting to keep channels coherent.
- Configure automatic restarts with systemd, Supervisor, or Kubernetes Deployments
  and use readiness probes that validate the `/ops/ws-health` endpoint the
  starter exposes.

---

## Publishing checklist

Use this checklist when cutting a release or updating starter documentation:

1. **Documentation parity** – ensure this guide and the individual starter
   `README.md` files match the behaviour of the published templates. Run each
   `composer create-project` command in a clean directory at least once per
   release to confirm installation succeeds without interaction.
2. **MkDocs integration** – add each starter guide to the site navigation in
   `mkdocs.yml` (under a "Starters" section) and enable collections so MkDocs
   renders cards or feature grids on the landing page. Verify `mkdocs serve` and
   `mkdocs build --strict` both succeed locally.
3. **Static-site deployment** – update Netlify, GitHub Pages, or the chosen host
   to trigger builds from the default branch. Provide the `pip install -r
   docs/requirements.txt` step (or inline dependency list) so the build image has
   MkDocs and theme plugins available.
4. **Automation** – wire smoke tests into CI that execute the `create-project`
   commands using Composer's `--no-interaction` flag. Cache Composer downloads to
   keep pipelines fast and surface template breakage immediately.
5. **Support rotation** – record starter-specific issues in the main issue
   tracker using the `starter` label. Publish FAQs and troubleshooting tips as
   they emerge so teams integrating the starters have quick answers.
