# Configuration Schema Overview (v1.0 Contract)

Bamboo treats every file under `etc/` as part of the stable contract. The
following tables document each schema, default values, and environment overrides
for the v1.0 release. Use `composer validate:config` (which calls
`php bin/bamboo config.validate`) to catch drift before deploying.

## Quick reference

| File | Purpose |
|------|---------|
| `etc/app.php` | Application identity, environment flags, log destination. |
| `etc/server.php` | OpenSwoole HTTP server host, port, worker counts, static file toggle. |
| `etc/cache.php` | Cache directory and route cache path. |
| `etc/middleware.php` | Global, group, and alias definitions for HTTP middleware. |
| `etc/modules.php` | Ordered list of application modules to boot. |
| `etc/redis.php` | Redis connection string and queue name. |
| `etc/database.php` | Optional database connection definitions. |
| `etc/http.php` | HTTP client defaults and named service endpoints. |
| `etc/metrics.php` | Prometheus namespace, storage driver, histogram buckets. |
| `etc/resilience.php` | Request timeouts, circuit breaker thresholds, health checks. |
| `etc/ws.php` | WebSocket server host and port. |
| `etc/auth.php` | JWT authentication defaults and user store configuration. |

## Schema details

### `etc/app.php`

| Key | Type | Default | Environment override |
|-----|------|---------|-----------------------|
| `name` | string | `Bamboo` | `APP_NAME` |
| `env` | string | `local` | `APP_ENV` |
| `debug` | bool | `true` | `APP_DEBUG` (parsed as boolean) |
| `key` | string | empty string | `APP_KEY` |
| `log_file` | string | `var/log/app.log` | `LOG_FILE` |

When `debug` is disabled, `app.key` must contain a non-empty secret; the
configuration validator enforces this requirement.

### `etc/server.php`

| Key | Type | Default | Environment override |
|-----|------|---------|-----------------------|
| `host` | string | `127.0.0.1` | `HTTP_HOST` |
| `port` | int | `9501` | `HTTP_PORT` |
| `workers` | int | CPU count | `HTTP_WORKERS` (`auto` uses detected CPU cores) |
| `task_workers` | int | `0` | `TASK_WORKERS` |
| `max_requests` | int | `10000` | `MAX_REQUESTS` |
| `static_enabled` | bool | `true` | `STATIC_ENABLED` |

### `etc/cache.php`

| Key | Type | Default | Notes |
|-----|------|---------|-------|
| `path` | string | `var/cache` | Directory for application cache artifacts. |
| `routes` | string | `var/cache/routes.cache.php` | Route cache produced by `routes.cache`. |

### `etc/middleware.php`

Returns an associative array with optional `global`, `groups`, and `aliases`
entries. Each value must resolve to fully-qualified class names or alias strings.
Modules append to these arrays via `ModuleInterface::middleware`. See the router
contract for ordering semantics.

### `etc/modules.php`

Returns a list of module class names implementing
`Bamboo\Module\ModuleInterface`. Modules are loaded in order and participate in
the lifecycle described in `docs/modules.md`.

### `etc/redis.php`

| Key | Type | Default | Environment override |
|-----|------|---------|-----------------------|
| `url` | string | `tcp://127.0.0.1:6379` | `REDIS_URL` |
| `queue` | string | `jobs` | `REDIS_QUEUE` |

### `etc/database.php`

Optional. When present it must provide a `default` connection key and a
`connections` map. The stock template configures a MySQL connection using
environment variables `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, and `DB_PASSWORD`.

### `etc/http.php`

| Key | Type | Description |
|-----|------|-------------|
| `default.timeout` | float | Per-request timeout in seconds. |
| `default.headers` | array | Key/value pairs applied to every outbound request. |
| `default.retries.max` | int | Maximum retry attempts for retriable status codes. |
| `default.retries.base_delay_ms` | int | Exponential backoff base delay in milliseconds. |
| `default.retries.status_codes` | array<int> | HTTP status codes that trigger a retry. |
| `services` | array<string, array> | Named services with overrides such as `base_uri` and `timeout`. |

### `etc/metrics.php`

| Key | Type | Default | Environment override |
|-----|------|---------|-----------------------|
| `namespace` | string | `bamboo` | `BAMBOO_METRICS_NAMESPACE` (not set by default) |
| `storage.driver` | string | `swoole_table` | `BAMBOO_METRICS_STORAGE` |
| `storage.swoole_table.value_rows` | int | `16384` | — |
| `storage.swoole_table.string_rows` | int | `2048` | — |
| `storage.swoole_table.string_size` | int | `4096` | — |
| `storage.apcu.prefix` | string | `bamboo_prom` | `BAMBOO_METRICS_APCU_PREFIX` |
| `histogram_buckets` | array<string, array<float>> | Default buckets keyed by metric name. |

### `etc/resilience.php`

| Key | Type | Default | Environment override |
|-----|------|---------|-----------------------|
| `timeouts.default` | float | `3.0` | `BAMBOO_HTTP_TIMEOUT_DEFAULT` |
| `timeouts.per_route` | array<string, float|array> | `['GET /api/httpbin' => ['timeout' => 20.0]]` | — |
| `circuit_breaker.enabled` | bool | `true` | `BAMBOO_CIRCUIT_BREAKER_ENABLED` |
| `circuit_breaker.failure_threshold` | int | `5` | `BAMBOO_CIRCUIT_BREAKER_FAILURES` |
| `circuit_breaker.success_threshold` | int | `1` | `BAMBOO_CIRCUIT_BREAKER_SUCCESS` |
| `circuit_breaker.cooldown_seconds` | float | `30.0` | `BAMBOO_CIRCUIT_BREAKER_COOLDOWN` |
| `circuit_breaker.per_route` | array<string, mixed> | `[]` | — |
| `health.dependencies` | array | `[]` | — |

Per-route overrides accept either a scalar timeout or an array with keys such as
`timeout`, `enabled`, and thresholds mirroring the default circuit breaker
settings. The stock configuration ships with a `GET /api/httpbin` override set
to 20 seconds so the sample concurrent HTTP client endpoint remains usable even
when OpenSwoole coroutine wait groups are unavailable.

### `etc/ws.php`

| Key | Type | Default | Environment override |
|-----|------|---------|-----------------------|
| `host` | string | `127.0.0.1` | `WS_HOST` |
| `port` | int | `9502` | `WS_PORT` |

### `etc/auth.php`

| Key | Type | Default | Environment override |
|-----|------|---------|-----------------------|
| `jwt.secret` | string | empty string | `AUTH_JWT_SECRET` |
| `jwt.ttl` | int | `3600` | `AUTH_JWT_TTL` |
| `jwt.issuer` | string | `Bamboo` | `AUTH_JWT_ISSUER` |
| `jwt.audience` | string | `BambooUsers` | `AUTH_JWT_AUDIENCE` |
| `jwt.storage.driver` | string | `json` | — |
| `jwt.storage.path` | string | `var/auth/users.json` | `AUTH_JWT_USER_STORE` |
| `jwt.registration.enabled` | bool | `true` | `AUTH_JWT_ALLOW_REGISTRATION` |
| `jwt.registration.default_roles` | array<string> | `[]` | — |

When deploying authentication in production, ensure `AUTH_JWT_SECRET` is set
before turning off `app.debug`. The `auth.jwt.setup` CLI command generates a
secret, publishes this configuration file, and seeds a starter user store.

## Validation hooks

- `Bamboo\Core\ConfigValidator` enforces the schema above. It raises
  `ConfigurationException` with aggregated error messages when constraints are
  violated.
- `php bin/bamboo config.validate` runs the validator against the current
  configuration tree and prints the result. The Composer alias
  `composer validate:config` is suitable for CI pipelines.
- PHPUnit coverage in `tests/Core/ConfigValidatorTest.php` and
  `tests/Console/ConfigValidateCommandTest.php` guards the validator behaviour.

## Migration and deprecation policy

- Renaming configuration keys requires a one-minor-release compatibility window.
  During the window, code must read both the legacy and the new key while
  emitting `E_USER_DEPRECATED` notices when the legacy key is used.
- Removing configuration files or sections is a breaking change and must be
  reserved for major releases.
- New configuration files should ship with validation logic and documentation
  updates across `docs/configuration/`, the upgrade guide, and the CLI reference.
- Operators should rerun `composer validate:config` after every upgrade and before
  deploying to catch missing env vars or schema drift.
# Configuration Schema Overview (v1.0 Contract)

Bamboo loads every PHP array under `etc/` through `Bamboo\Core\Config`,
normalising the results into a single tree that backs the `config()` helper and
module bootstrap sequence.【F:src/Core/Config.php†L5-L107】 Runtime validation
happens during bootstrap via `Bamboo\Core\ConfigValidator`, so schema changes
must remain in lock-step with that guardrail and its PHPUnit coverage.【F:bootstrap/app.php†L10-L30】【F:tests/Core/ConfigValidatorTest.php†L10-L220】

## Loader behaviour and defaults

* `Config::loadConfiguration()` requires `etc/app.php`, `server.php`, `cache.php`,
  `redis.php`, `ws.php`, and `http.php`. Optional files (`database.php`,
  `middleware.php`, `metrics.php`, and `resilience.php`) fall back to sensible
  defaults when missing to keep new projects bootable.【F:src/Core/Config.php†L83-L107】
* When the metrics or resilience configuration files are absent, Bamboo injects
  in-memory Prometheus storage and baseline timeout/circuit-breaker settings so
  observability and safeguards still work in local development environments.【F:src/Core/Config.php†L89-L107】
* Environment variables are read directly inside the configuration files, so the
  tables below double as the canonical mapping between `.env` keys and runtime
  behaviour.

## etc/app.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `name` | string | `"Bamboo"` | `APP_NAME` |
| `env` | string | `"local"` | `APP_ENV` |
| `debug` | bool | `true` (coerced via `FILTER_VALIDATE_BOOLEAN`) | `APP_DEBUG` |
| `key` | string | empty string | `APP_KEY` |
| `log_file` | string (path) | `var/log/app.log` relative to the project root | `LOG_FILE` |

**Notes**

* `ConfigValidator` requires `app.key` to be non-empty whenever `app.debug` is
  `false` to ensure encrypted cookies and signed payloads remain safe in
  production environments.【F:tests/Core/ConfigValidatorTest.php†L62-L86】

## etc/server.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `host` | non-empty string | `"127.0.0.1"` | `HTTP_HOST` |
| `port` | integer 1–65535 | `9501` | `HTTP_PORT` |
| `workers` | positive integer | CPU core count (auto-detected) | `HTTP_WORKERS` (`"auto"` keeps detection) |
| `task_workers` | integer ≥ 0 | `0` | `TASK_WORKERS` |
| `max_requests` | integer ≥ 1 | `10000` | `MAX_REQUESTS` |
| `static_enabled` | bool | `true` | `STATIC_ENABLED` |

**Notes**

* Worker auto-scaling calls OpenSwoole helpers (or `nproc`) during bootstrap, so
  container images must allow that detection step to execute.【F:etc/server.php†L3-L18】
* Validation enforces that `server.host` is non-empty and the port falls within
  the TCP range before the HTTP server starts listening.【F:tests/Core/ConfigValidatorTest.php†L18-L41】

## etc/ws.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `host` | non-empty string | `"127.0.0.1"` | `WS_HOST` |
| `port` | integer 1–65535 | `9502` | `WS_PORT` |

## etc/auth.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `jwt.secret` | string | `""` | `AUTH_JWT_SECRET` |
| `jwt.ttl` | positive integer | `3600` | `AUTH_JWT_TTL` |
| `jwt.issuer` | string | `"Bamboo"` | `AUTH_JWT_ISSUER` |
| `jwt.audience` | string | `"BambooUsers"` | `AUTH_JWT_AUDIENCE` |
| `jwt.storage.driver` | string | `"json"` | — |
| `jwt.storage.path` | string (path) | `"var/auth/users.json"` | `AUTH_JWT_USER_STORE` |
| `jwt.registration.enabled` | bool | `true` | `AUTH_JWT_ALLOW_REGISTRATION` |
| `jwt.registration.default_roles` | array<string> | `[]` | — |

**Notes**

* The scaffolded `auth.jwt.setup` command publishes this configuration, ensures
  `AUTH_JWT_SECRET` is generated, and seeds a default `admin` user. Rotate the
  secret whenever credentials change to invalidate old tokens.
* When `app.debug` is disabled, the configuration validator requires
  `jwt.secret` to be non-empty, mirroring the enforcement applied to
  `app.key`.

## etc/cache.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `path` | string (directory) | `var/cache` relative to the project root | — |
| `routes` | string (file path) | `var/cache/routes.cache.php` relative to the project root | — |

**Notes**

* `ConfigValidator` guarantees that `cache.routes` is a non-empty string so the
  route caching command cannot silently fail when writing to disk.【F:tests/Core/ConfigValidatorTest.php†L42-L55】

## etc/redis.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `url` | non-empty string | `"tcp://127.0.0.1:6379"` | `REDIS_URL` |
| `queue` | non-empty string | `"jobs"` | `REDIS_QUEUE` |

**Notes**

* Application feature tests seed `REDIS_URL` with an in-memory driver to assert
  queue behaviour end-to-end, so keep the key stable for test fixtures and
  operator overrides.【F:tests/Http/ApplicationRoutesTest.php†L20-L74】

## etc/database.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `default` | string | `"mysql"` | `DB_CONNECTION` |
| `connections.mysql.driver` | string | `"mysql"` | — |
| `connections.mysql.host` | non-empty string | `"127.0.0.1"` | `DB_HOST` |
| `connections.mysql.port` | string/integer | `"3306"` | `DB_PORT` |
| `connections.mysql.database` | string | `"app"` | `DB_DATABASE` |
| `connections.mysql.username` | string | `"root"` | `DB_USERNAME` |
| `connections.mysql.password` | string | empty string | `DB_PASSWORD` |
| `connections.mysql.charset` | string | `"utf8mb4"` | — |
| `connections.mysql.collation` | string | `"utf8mb4_unicode_ci"` | — |

**Notes**

* The configuration ships a single MySQL connection stub. Additional connections
  should use the same nested structure so `config('database.connections.*')`
  remains predictable for packages that extend the database layer.【F:etc/database.php†L3-L16】

## etc/http.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `default.timeout` | positive float | `5.0` | — |
| `default.headers.User-Agent` | string | `"Bamboo-HTTP/1.0"` | — |
| `default.retries.max` | positive integer | `2` | — |
| `default.retries.base_delay_ms` | integer ≥ 0 | `150` | — |
| `default.retries.status_codes` | list<int> | `[429, 500, 502, 503, 504]` | — |
| `services.httpbin.base_uri` | string (URL) | `"https://httpbin.org"` | — |
| `services.httpbin.timeout` | positive float | `5.0` | — |

**Notes**

* `ConfigValidator` enforces a positive HTTP timeout to prevent zero-delay
  requests from overwhelming downstream services.【F:tests/Core/ConfigValidatorTest.php†L56-L79】
* Additional HTTP client profiles should follow the `services.{name}` pattern so
  dependency injection remains straightforward.

## etc/metrics.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `namespace` | non-empty string | `"bamboo"` | — |
| `storage.driver` | non-empty string | `"swoole_table"` | `BAMBOO_METRICS_STORAGE` |
| `storage.swoole_table.value_rows` | positive integer | `16384` | — |
| `storage.swoole_table.string_rows` | positive integer | `2048` | — |
| `storage.swoole_table.string_size` | positive integer | `4096` | — |
| `storage.apcu.prefix` | string | `"bamboo_prom"` | `BAMBOO_METRICS_APCU_PREFIX` |
| `histogram_buckets.default` | list<float> | `[0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0]` | — |
| `histogram_buckets.bamboo_http_request_duration_seconds` | list<float> | `[0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0]` | — |

**Notes**

* The metrics namespace feeds directly into the Prometheus collectors used by
  `HttpMetrics` and `CircuitBreakerMetrics`, so renaming it will change emitted
  timeseries labels.【F:etc/metrics.php†L1-L44】【F:tests/Roadmap/V0_4/TimeoutMiddlewareTest.php†L24-L66】
* Validation checks that namespace, driver, and bucket arrays are shaped
  correctly to catch typos before metrics collection begins.【F:tests/Core/ConfigValidatorTest.php†L88-L165】

## etc/resilience.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `timeouts.default` | positive float | `3.0` | `BAMBOO_HTTP_TIMEOUT_DEFAULT` (optional) |
| `timeouts.per_route` | array<string, float|array{timeout?:float}> | empty array | — |
| `circuit_breaker.enabled` | bool | `true` | `BAMBOO_CIRCUIT_BREAKER_ENABLED` |
| `circuit_breaker.failure_threshold` | positive integer | `5` | `BAMBOO_CIRCUIT_BREAKER_FAILURES` |
| `circuit_breaker.cooldown_seconds` | float ≥ 0 | `30.0` | `BAMBOO_CIRCUIT_BREAKER_COOLDOWN` |
| `circuit_breaker.success_threshold` | positive integer | `1` | `BAMBOO_CIRCUIT_BREAKER_SUCCESS` |
| `circuit_breaker.per_route` | array<string, mixed> | empty array | — |
| `health.dependencies` | array<string, mixed> | empty array | — |

**Notes**

* Timeout and circuit-breaker overrides are keyed by the router's `METHOD /path`
  notation so middleware can map telemetry to specific routes.【F:etc/resilience.php†L6-L26】【F:tests/Roadmap/V0_4/TimeoutMiddlewareTest.php†L24-L85】
* Validation checks numeric ranges and per-route overrides to prevent
  misconfigured resilience policies from reaching production.【F:tests/Core/ConfigValidatorTest.php†L126-L220】

## etc/middleware.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| `global` | list<class-string> | Request/metrics/resilience middleware stack | — |
| `groups.web` | list<class-string> | `Bamboo\Web\Middleware\SignatureHeader::class` | — |
| `aliases` | array<string, class-string> | empty array | — |

**Notes**

* Modules contribute additional middleware via `Config::mergeMiddleware()`, and
  the module lifecycle test suite locks in the merge order to protect downstream
  applications.【F:etc/middleware.php†L1-L28】【F:src/Core/Config.php†L24-L82】【F:tests/Core/ApplicationModulesTest.php†L23-L60】

## etc/modules.php

| Key | Type | Default | Environment variable |
| --- | --- | --- | --- |
| root value | list<class-string<ModuleInterface>> | empty list | — |

**Notes**

* `bootstrap/app.php` fails fast if the file returns anything other than an
  array of class strings, ensuring module registration is deterministic during
  bootstrap.【F:bootstrap/app.php†L22-L30】

## Cross-file considerations

* Middleware and resilience policies are tightly coupled: the default middleware
  pipeline wires `HttpMetricsCollector`, `CircuitBreakerMiddleware`, and
  `TimeoutMiddleware`, which read from both `etc/middleware.php` and
  `etc/resilience.php`. Changing one file without updating the other can cause
  runtime mismatches in metrics labels or resilience defaults.【F:etc/middleware.php†L17-L28】【F:etc/resilience.php†L6-L26】
* Queue-heavy routes depend on `redis.queue` for naming conventions in tests and
  background workers. Keep the queue key aligned with any worker configuration or
  job dispatch code that reads `config('redis.queue')`.【F:tests/Http/ApplicationRoutesTest.php†L52-L74】
* Metrics configuration interacts with route instrumentation; updating histogram
  buckets should be coordinated with alert thresholds and any downstream metrics
  aggregation pipelines.【F:etc/metrics.php†L31-L41】【F:tests/Roadmap/V0_4/MetricsEndpointTest.php†L18-L44】

## Validation and tooling roadmap

* **Runtime enforcement** – `bootstrap/app.php` validates configuration on every
  bootstrap. All errors are emitted to standard error before the process aborts,
  preventing partial boots with invalid settings.【F:bootstrap/app.php†L10-L30】
* **Composer script (planned)** – add a `"validate:config"` script entry to
  `composer.json` that runs `php bin/config-validate`, a thin wrapper invoking
  `ConfigValidator` against `etc/`. The script will:
  1. bootstrap Composer autoloading;
  2. instantiate `Config` and `ConfigValidator`;
  3. exit with code `1` when validation fails (after printing aggregated
     messages).
  Integrate the command into CI pipelines and pre-deploy hooks so operators can
  catch schema drift without booting the application.
* **PHPUnit coverage** – `tests/Core/ConfigValidatorTest.php` exercises all guard
  rails, from HTTP timeout ranges to circuit-breaker thresholds, while
  `tests/Core/ApplicationModulesTest.php` verifies middleware merging behaviour
  that depends on the configuration tree. Keep new configuration keys backed by
  similar tests before marking the schema stable.【F:tests/Core/ConfigValidatorTest.php†L10-L220】【F:tests/Core/ApplicationModulesTest.php†L23-L60】

## Migration and deprecation policy

* **Key renames** – ship shims that read both the legacy and replacement keys.
  Deprecations should emit a log entry when the old key is used and remove the
  shim no earlier than the next minor release. Document the overlap window in the
  upgrade guide and release notes.
* **Feature-flagged fallbacks** – introduce new behaviour behind opt-in flags.
  Keep defaults aligned with previous releases until the feature graduates, then
  flip the default in a minor release with clear migration instructions.
* **Communication requirements** – every configuration migration must surface in
  release notes, the roadmap tracking issue, and the dedicated upgrade guide
  section. When runtime shims log notices, include actionable remediation steps
  and links to relevant documentation.
* **Validation alignment** – update `ConfigValidator` (and its tests) alongside
  any schema change. The composer validation command should be updated in the
  same pull request so CI immediately guards the new contract.
