# Bamboo CLI Reference (v1.0 API Freeze Prep)

> **Status:** Stub for the v1.0.0 release track. Populate with full command contracts before announcing the release candidate.

Bamboo's dot-notation console is the operational entry point for every deployment. This outline tracks the documentation work required to freeze the CLI surface for the v1.0 line.

## Stability index

- [ ] Document the stability tier (stable / preview / internal) for every command surfaced by `bin/bamboo list`.
- [ ] Record the promotion or demotion process for commands that move between tiers.
- [ ] Capture the expectations for semantic versioning and how CLI changes are communicated to operators.

## Command contracts

| Command | Purpose | Input contract notes | Output contract notes | Status |
|---------|---------|----------------------|-----------------------|--------|
| `app.key.make` | Generates an application key. | _TODO: describe required flags and environmental prerequisites._ | _TODO: describe key format and success output._ | ☐ Draft
| `cache.purge` | Clears runtime caches. | _TODO: list targeted caches and invalidation hooks._ | _TODO: describe success/error payloads._ | ☐ Draft
| `client.call` | Issues outbound HTTP calls. | _TODO: capture argument validation and timeout semantics._ | _TODO: document JSON schema for responses._ | ☐ Draft
| `dev.watch` | Supervises long-running commands. | _TODO: specify file watching configuration and overrides._ | _TODO: outline restart notifications and exit codes._ | ☐ Draft
| `http.serve` | Boots the HTTP server. | _TODO: enumerate flags and environment requirements._ | _TODO: define lifecycle events and structured logs._ | ☐ Draft
| `pkg.info` | Surfaces Composer package metadata. | _TODO: record filtering options._ | _TODO: record table/JSON output variants._ | ☐ Draft
| `queue.work` | Runs queue workers. | _TODO: note worker scaling and queue bindings._ | _TODO: describe job acknowledgement behaviour._ | ☐ Draft
| `routes.cache` | Caches route definitions. | _TODO: record warm-up prerequisites and cache location._ | _TODO: capture success messaging and errors._ | ☐ Draft
| `routes.show` | Dumps the active route table. | _TODO: outline filters and formatting options._ | _TODO: describe table schema and column meanings._ | ☐ Draft
| `schedule.run` | Triggers scheduled jobs. | _TODO: document cron resolution and locking._ | _TODO: record reporting payloads._ | ☐ Draft
| `ws.serve` | Boots the WebSocket server. | _TODO: describe handshake configuration and workers._ | _TODO: capture lifecycle events._ | ☐ Draft

_Add additional commands to the table as they are added to the console kernel._

## Deprecation policy

- [ ] Define the deprecation window for renamed or removed commands.
- [ ] Document how deprecated aliases emit warnings and how long they remain available.
- [ ] Outline the communication plan (release notes, runtime notices, upgrade guide call-outs).

## Testing hooks

- [ ] Link contract tests that protect CLI input/output formats.
- [ ] Reference smoke scripts for automated validation of command behaviours.

