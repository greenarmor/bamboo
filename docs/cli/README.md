# Bamboo CLI Reference (v1.0 API Freeze Prep)

> **Status:** Command contracts and stability tiers documented for the v1.0.0 release track freeze review.

Bamboo's dot-notation console is the operational entry point for every deployment. The sections below describe the current stability guarantees, end-to-end contracts, and the guardrails in place to detect regressions before v1.0 ships.

## Stability index

### Command tiers

| Command | Tier | Notes | Test coverage |
|---------|------|-------|---------------|
| `app.key.make` | Stable | Required for provisioning secrets in production deployments. | _Contract gap – add coverage in a future cycle._ |
| `auth.jwt.setup` | Preview | Publishes JWT auth config, seeds a default user store, and registers the module. | [`tests/Console/AuthJwtSetupCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/AuthJwtSetupCommandTest.php) |
| `cache.purge` | Stable | Clears framework caches without touching user data. Safe for automated rollouts. | _Contract gap – add coverage in a future cycle._ |
| `client.call` | Preview | Intended for troubleshooting and lacks retry/back-off knobs. Marked preview until the ergonomics settle. | _Contract gap – add coverage in a future cycle._ |
| `database.setup` | Preview | Interactive wizard for configuring database connections, schemas, and seed data. | [`tests/Console/DatabaseSetupCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/DatabaseSetupCommandTest.php) |
| `dev.watch` | Preview | Development helper that may add flags as the workflow evolves. | [`tests/Console/DevWatchTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/DevWatchTest.php) |
| `http.serve` | Stable | Entry point for OpenSwoole HTTP hosts. Behaviour is locked for v1.x. | [`tests/Console/HttpServeCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/HttpServeCommandTest.php) |
| `landing.meta` | Preview | Dumps landing page metadata for inspection or seeding CMS fixtures. | [`tests/Console/LandingMetaCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/LandingMetaCommandTest.php) |
| `pkg.info` | Internal | Diagnostics command that scrapes Composer metadata. Not covered by semver guarantees. | _Internal-only – explicitly excluded from contract tests._ |
| `queue.work` | Stable | Runs background workers and honours deployment-critical flags. | [`tests/Console/QueueWorkCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/QueueWorkCommandTest.php) |
| `routes.cache` | Stable | Required for warm boots in production. | _Contract gap – add coverage in a future cycle._ |
| `routes.show` | Stable | Used in CI drift checks to confirm routing tables. | [`tests/Console/RoutesShowCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/RoutesShowCommandTest.php) |
| `schedule.run` | Stable | Cron entry point; prints structured ticks for log shipping. | _Contract gap – add coverage in a future cycle._ |
| `ws.serve` | Preview | Simple echo server meant for local verification. Subject to change when full duplex APIs land. | _Contract gap – add coverage in a future cycle._ |

### Promotion and demotion workflow

1. **Proposal** – File an ADR describing the desired tier change, covering operator impact and telemetry considerations.
2. **Experimentation** – Ship the command as Preview for at least one minor release. Collect feedback via GitHub Discussions and issue templates.
3. **Stabilisation** – Add or extend the automated tests listed above to cover the promoted surface and document the final contract.
4. **Announcement** – Note the tier change in the upgrade guide and changelog. If demoting to Internal, provide at least one minor release notice before removal.

### Communication expectations

- Stable commands adhere to semantic versioning. Breaking changes require a new major release and an opt-in migration path.
- Preview commands may extend their flag sets or adjust output formatting between minor releases. Breaking changes must be highlighted in release notes and the [v1.0 upgrade guide](../upgrade/v1.0.md).
- Internal commands can change without notice. They are excluded from public marketing materials and may emit warnings when invoked outside development.
- Deprecations use a two-cycle runway: aliases or shims remain in place for at least one minor release, and warnings are printed on every invocation.
- Communication channels include: release notes, upgrade guide call-outs, in-tool warnings, and announcements in the community discussion board.

## Command contracts

The following sections capture the full contract for every command currently registered in `src/Console/Kernel.php`. Each block documents inputs, outputs, side effects, and automated guardrails where present.

### `app.key.make`

- **Purpose:** Generate a random application key and persist it to `.env` (creating the file from `.env.example` if necessary).
- **Inputs:**
  - Optional `--if-missing` flag skips regeneration when `APP_KEY` already has a non-empty value.
  - Relies on the process working directory (defaults to the repository root) and file-system write access.
- **Outputs & exit codes:**
  - Prints `APP_KEY already present; skipping (--if-missing).` when `--if-missing` short-circuits (exit code `0`).
  - Otherwise prints `APP_KEY set in <path>.` on success (exit code `0`).
- **Side effects:**
  - Creates or mutates `.env`; writes base64-encoded 32-byte keys; ensures the parent directory exists.
  - Triggered automatically by Composer's `post-install-cmd` hook.
- **Guardrails:** Automated coverage pending – add integration smoke tests that assert key format and idempotency.

### `cache.purge`

- **Purpose:** Delete generated cache artifacts (including `var/cache/routes.cache.php`).
- **Inputs:**
  - No command arguments. Reads the purge target from `cache.path` in [`etc/cache.php`](https://github.com/greenarmor/bamboo/blob/main/etc/cache.php).
  - Requires file-system permissions to remove cache files.
- **Outputs & exit codes:**
  - Prints `No cache directory.` when the directory is missing (exit code `0`).
  - Prints `Cache purged.` after removing files (exit code `0`).
- **Side effects:**
  - Unlinks every file under the configured cache directory but leaves the directory intact.
  - Safe to run repeatedly; invoked by Composer's `post-update-cmd` hook.
- **Guardrails:** Contract tests pending – add coverage that seeds cache files and verifies they are removed without affecting unrelated directories.

### `client.call`

- **Purpose:** Execute a single HTTP GET request using Bamboo's PSR-18 client stack for troubleshooting.
- **Inputs:**
  - Required `--url=` flag specifying the absolute URL to fetch.
  - Uses the container `http.client` binding and Nyholm PSR-7 factories; honours any HTTP client configuration registered in the service container.
- **Outputs & exit codes:**
  - Prints `Usage: php bin/bamboo client.call --url=https://...` and exits with code `1` when the URL flag is missing.
  - On success, streams the status line (`HTTP/<version> <code>`) followed by the raw response body to stdout (exit code `0`).
- **Side effects:**
  - Issues a network request; does not mutate application state.
- **Guardrails:** No automated contract tests yet. Add curl-style fixtures to assert status line formatting before promoting out of Preview.

<a id="database.setup"></a>
### `database.setup`

- **Purpose:** Guide developers through configuring database connections, defining tables, and seeding starter data without hand-editing configuration files.
- **Inputs:**
  - Interactive prompts request the database driver (`mysql`, `pgsql`, or `sqlite`), connection details (host, port, database, username, password, or SQLite path), and optionally loop through table definitions.
  - For each table, the wizard collects column names, basic column types (increments, integer, bigInteger, string, text, boolean, timestamp), nullability, default values, and seed rows. Yes/no prompts control whether additional tables or rows are added.
- **Outputs & exit codes:**
  - Prints a banner, echoes configuration progress, and reports when tables are created or skipped. Returns `0` on success. Returns `1` when the project root cannot be found or when connectivity checks against the selected database fail.
- **Side effects:**
  - Updates `.env` with the selected `DB_*` values, rewrites `etc/database.php` to match the chosen connection, ensures SQLite directories exist, verifies connectivity using `Illuminate\Database\Capsule\Manager`, creates new tables, and seeds rows only when the target table is empty.
- **Guardrails:** [`tests/Console/DatabaseSetupCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/DatabaseSetupCommandTest.php) exercises configuration persistence, schema creation, seed insertion, and idempotent re-runs.
- **Usage example:** Run `php bin/bamboo database.setup` and follow the prompts to provision a development database.

### `dev.watch`

- **Purpose:** Supervise a long-running command (defaults to `php bin/bamboo http.serve`) and restart it when watched files change.
- **Inputs:**
  - Optional flags:
    - `--debounce=<ms>` (or `--debounce <ms>`) sets the restart debounce interval; defaults to `500` milliseconds.
    - `--watch=<paths>` (or `--watch <paths>`) accepts a comma-separated list of files/directories relative to the project root. Defaults to `src,etc,routes,bootstrap,public`.
    - `--command=<cmd>` (or `--command <cmd>`) overrides the supervised command. Arguments after `--` are treated as the full command line.
    - `--help`/`-h` prints usage documentation.
  - Requires a PSR-3 logger bound to `log`; optionally uses the `inotify` extension, otherwise falls back to Symfony Finder polling.
- **Outputs & exit codes:**
  - Emits informational and warning logs through the PSR-3 logger (see tests for structured fields). Console output appears when `--help` is requested or when dependency resolution fails.
  - Returns exit code `0` on normal shutdown; returns `1` when option parsing or watcher setup fails.
- **Side effects:**
  - Spawns and restarts the supervised process; responds to `SIGINT`/`SIGTERM` when supported.
- **Guardrails:** Behaviour validated by [`tests/Console/DevWatchTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/DevWatchTest.php), covering option parsing, restart loops, and logger payloads.

### `http.serve`

- **Purpose:** Boot the OpenSwoole HTTP server with Bamboo's application kernel.
- **Inputs:**
  - Reads server configuration from [`etc/server.php`](https://github.com/greenarmor/bamboo/blob/main/etc/server.php) and environment variables (`HTTP_HOST`, `HTTP_PORT`, `HTTP_WORKERS`, `TASK_WORKERS`, `MAX_REQUESTS`, `STATIC_ENABLED`).
  - Honours the `DISABLE_HTTP_SERVER_START` env flag for tests and dry runs; when truthy, skips calling `OpenSwoole\HTTP\Server::start()`.
- **Outputs & exit codes:**
  - Prints `Bamboo HTTP online at http://<host>:<port>` when the server is initialised.
  - Additional lifecycle messages are emitted via OpenSwoole event callbacks; the command returns `0` when the bootstrap script completes without uncaught exceptions.
- **Side effects:**
  - Starts the async server loop; records instrumentation via `Bamboo\Swoole\ServerInstrumentation` and toggles readiness probes exposed by `Bamboo\Web\Health\HealthState`.
- **Guardrails:** [`tests/Console/HttpServeCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/HttpServeCommandTest.php) verifies the boot banner, instrumentation hooks, and environment flag handling.

### `landing.meta`

- **Purpose:** Render the structured metadata payload that powers the landing page and JSON API.
- **Inputs:**
  - Optional first argument sets the descriptor `type` (for example: `framework`, `article`, `food`, `book`, or `about`).
  - Additional `key=value` pairs override descriptor attributes. They can be provided either via CLI arguments or the `landing.content` configuration block.
- **Outputs & exit codes:**
  - Prints a JSON document containing the merged metadata defaults (including timestamps) on success (exit code `0`).
  - Emits `No metadata available.` when the descriptor resolves to an empty payload (exit code `0`).
  - Returns exit code `1` if metadata encoding fails.
- **Side effects:**
  - Read-only. Useful for seeding CMS fixtures or verifying API responses during development.
- **Guardrails:** [`tests/Console/LandingMetaCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/LandingMetaCommandTest.php) asserts descriptor parsing, default payloads (including the `about` mission), and override behaviour.
- **Usage example:**
  - `php bin/bamboo landing.meta about mission="Prototype async PHP workflows"`

### `pkg.info`

- **Purpose:** Display installed Composer packages for diagnostics.
- **Inputs:**
  - No command arguments. Reads `vendor/composer/installed.json` relative to the repository root.
- **Outputs & exit codes:**
  - Prints `No vendor packages installed yet.` when the file is absent (exit code `0`).
  - Otherwise emits one line per package using `printf("%-40s %s\n", name, version)` (exit code `0`).
- **Side effects:**
  - Read-only. Intended for maintainers; omitted from public automation examples.
- **Guardrails:** Marked Internal – defer automated coverage until the command is promoted for general use.

### `queue.work`

- **Purpose:** Run a Redis-backed worker that blocks on a queue and processes payloads sequentially.
- **Inputs:**
  - Flags:
    - `--once` processes a single job then exits.
    - `--max-jobs=<n>` (or `--max-jobs <n>`) limits the number of jobs processed before shutdown.
  - Pulls Redis connection details from [`etc/redis.php`](https://github.com/greenarmor/bamboo/blob/main/etc/redis.php) or container overrides of `redis.client.factory`.
  - Requires a Predis client factory bound in the container.
- **Outputs & exit codes:**
  - Prints `Worker listening on '<queue>'` when the loop starts.
  - Logs each job as `Job: <payload>` when dequeued.
  - Returns exit code `0` after the loop finishes (either via limits or shutdown signals).
- **Side effects:**
  - Performs blocking `BLPOP` calls against the configured Redis queue.
- **Guardrails:** [`tests/Console/QueueWorkCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/QueueWorkCommandTest.php) seeds an in-memory Predis server and asserts flag handling plus output formatting.

### `routes.cache`

- **Purpose:** Serialize the application's route table to a cache file for faster boots.
- **Inputs:**
  - No command arguments. Uses `cache.routes` from [`etc/cache.php`](https://github.com/greenarmor/bamboo/blob/main/etc/cache.php) to determine the target file.
  - Requires the `router` service to implement a `cacheTo(<path>)` method.
- **Outputs & exit codes:**
  - Prints `Routes cached -> <file>` on success (exit code `0`).
  - Prints `Routes not cached: <error>` and exits `1` when the router throws a `RuntimeException`.
- **Side effects:**
  - Overwrites the cache file with the serialized route map.
- **Guardrails:** Automated coverage pending – add tests that stub the router to verify both success and failure branches.

### `routes.show`

- **Purpose:** List every registered route along with its handler for audit trails and CI verification.
- **Inputs:**
  - No flags today. Pulls routes from the `router` service's `all()` method.
- **Outputs & exit codes:**
  - Prints each route using `printf("%-6s %-30s %s\n", method, path, handlerLabel)`.
  - Returns exit code `0` after iterating all routes.
- **Side effects:**
  - Read-only.
- **Guardrails:** [`tests/Console/RoutesShowCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/RoutesShowCommandTest.php) confirms the formatted output for controller and closure handlers.

### `schedule.run`

- **Purpose:** Cron entry point that triggers scheduled tasks and emits a heartbeat for observability pipelines.
- **Inputs:**
  - No arguments. Intended to be executed by system cron or a job scheduler.
- **Outputs & exit codes:**
  - Prints `[YYYY-mm-dd HH:MM:SS] schedule.run tick` with the current timestamp (exit code `0`).
- **Side effects:**
  - Placeholder implementation – integrate with the scheduler subsystem before GA.
- **Guardrails:** Contract tests pending – add coverage once the scheduler hooks are wired in.

### `ws.serve`

- **Purpose:** Start a simple OpenSwoole WebSocket echo server for development validation.
- **Inputs:**
  - Reads host/port from [`etc/ws.php`](https://github.com/greenarmor/bamboo/blob/main/etc/ws.php) and associated environment variables (`WS_HOST`, `WS_PORT`).
- **Outputs & exit codes:**
  - Prints `WS on ws://<host>:<port>` when the server starts.
  - Emits `WS <fd> open/closed` events to stdout as clients connect/disconnect.
  - Returns exit code `0` unless OpenSwoole throws an error during bootstrap.
- **Side effects:**
  - Starts an async WebSocket server that echoes incoming messages back to the sender.
- **Guardrails:** Currently untested; treat all changes as Preview until automated smoke coverage is added.

## Deprecation policy and communication workflow

1. **Announcement window:** Deprecated commands or flags are announced one minor release before enforcement. The upgrade guide, changelog, and CLI help output must include the sunset date.
2. **Runtime warnings:** Deprecated commands emit `E_USER_DEPRECATED` notices (for PHP consumers) and print a warning banner on stdout identifying the preferred replacement.
3. **Alias lifetime:** When renaming commands, maintain the legacy alias for at least one minor release. The alias delegates to the new implementation and surfaces the warning banner described above.
4. **Removal:** Once the grace period lapses, remove the alias in a minor release only if a major version bump is imminent; otherwise, defer removal to the next major release to preserve semver guarantees.
5. **Verification:** Update or add PHPUnit coverage alongside the change. Contract tests in `tests/Console/` should assert both the warning copy and the exit codes for deprecated paths before promotion.

## Testing hooks

- `http.serve`, `queue.work`, `routes.show`, and `dev.watch` are covered by PHPUnit suites under [`tests/Console/`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/). These tests lock the emitted banners, option parsing, and integration points documented above.
- Remaining commands are flagged as contract gaps; track additions in the roadmap and extend the suite as they stabilise.
- When adding new commands, include fixtures or smoke scripts that exercise their happy path, and wire them into `composer test` to keep the freeze enforceable.
### `auth.jwt.setup`

- **Purpose:** Publish JWT authentication scaffolding so new projects have login endpoints and a seeded user store.
- **Inputs:**
  - No flags today. Operates relative to the project root.
- **Outputs & exit codes:**
  - Generates an `AUTH_JWT_SECRET` in `.env` when missing, printing `Generated AUTH_JWT_SECRET in .env.` (exit code `0`).
  - When the secret already exists, prints `AUTH_JWT_SECRET already present; leaving existing value.` (exit code `0`).
  - Always prints `JWT authentication scaffolding is ready to use.` when the run completes successfully.
- **Side effects:**
  - Creates `etc/auth.php` if absent by copying `stubs/auth/jwt-auth.php`.
  - Seeds `var/auth/users.json` with an `admin` user (password `password`) when the JSON driver is active and the store is empty, leaving non-JSON backends untouched so you can apply migrations manually.
  - Writes driver-specific configuration (JSON, MySQL, PostgreSQL, Firebase, NoSQL) to `etc/auth.php` based on `AUTH_JWT_STORAGE_DRIVER` and related environment variables.
  - Registers `Bamboo\Auth\Jwt\JwtAuthModule` in `etc/modules.php` if it is not already listed.
  - Writes a random 64-character hex secret to `.env` when `AUTH_JWT_SECRET` is missing.
  - Full walkthrough: [JWT Authentication CLI Toolkit](jwt-auth-toolkit.md).
- **Guardrails:** [`tests/Console/AuthJwtSetupCommandTest.php`](https://github.com/greenarmor/bamboo/blob/main/tests/Console/AuthJwtSetupCommandTest.php) covers secret generation, module registration, and idempotent user store seeding.

### `cache.purge`
