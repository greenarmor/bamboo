# Module Extension API (v1.0 Contract)

Modules package reusable integrations for Bamboo. Each module implements
`Bamboo\Module\ModuleInterface` and is loaded through `Application::bootModules`
after the framework has registered its core providers. This document freezes the
contract for v1.0 module authors.

## Lifecycle hooks

`ModuleInterface` defines three methods:

1. `register(Application $app): void`
   - Called immediately after the module is instantiated.
   - Register container singletons, bindings, and configuration defaults.
   - Avoid performing I/O or resolving heavy services.
2. `boot(Application $app): void`
   - Invoked after **all** modules have finished registration.
   - Safe place to resolve dependencies registered by other modules.
   - Ideal for wiring event listeners, scheduling jobs, or introspecting
     configuration.
3. `middleware(): array`
   - Returns global middleware, named groups, or alias definitions using the
     structure consumed by `Config::mergeMiddleware()`.
   - Called once per module; the return value is merged into `config('middleware')`
     before the HTTP kernel builds route-specific stacks.

Modules are instantiated in the order they appear in `etc/modules.php`.
`register()` is executed for each module in sequence, then `boot()` is executed
in the same order. This guarantees deterministic setup and mirrors the behaviour
covered by `tests/Core/ApplicationModulesTest.php`.

## Discovery and configuration

Modules are discovered through `etc/modules.php` which returns a list of
fully-qualified class names:

```php
<?php
return [
    App\Infrastructure\MetricsModule::class,
    Vendor\Package\QueueModule::class,
];
```

Configuration guidelines:

- Use dedicated `etc/` files (e.g. `etc/queue.php`) or nest module configuration
  under a namespaced key inside `config('app')`. Document the expected schema in
  your package README and expose validation logic when appropriate.
- Publish sensible defaults inside `register()` so downstream applications can
  opt-in incrementally.
- When contributing middleware, return arrays shaped like:

```php
public function middleware(): array
{
    return [
        'global' => [\Vendor\Middleware\Audit::class],
        'groups' => [
            'api' => [\Vendor\Middleware\Authorize::class],
        ],
        'aliases' => [
            'vendor.audit' => \Vendor\Middleware\Audit::class,
        ],
    ];
}
```

## Semantic versioning guidance

Modules follow normal semver rules:

- Backwards-incompatible changes to exported PHP classes, configuration keys, or
  middleware aliases require a major version bump.
- Adding optional constructor arguments, new configuration keys with sensible
  defaults, or additional middleware entries is safe in minor releases.
- When introducing new hooks, provide default implementations so existing
  consumers continue to operate without modification.

Compatibility testing should cover:

- `phpunit` suites that assert container bindings and middleware are registered.
- Static analysis (PHPStan/Psalm) that exercises public APIs.
- Smoke tests against a Bamboo skeleton app to ensure the module boots under
  `php bin/bamboo http.serve`.

## Deprecation policy

- Announce deprecations in your module's changelog and documentation.
- Emit `E_USER_DEPRECATED` notices the first time deprecated functionality is
  used.
- Maintain deprecated methods or aliases for at least one minor release. Removal
  must coincide with a major release and an upgrade guide entry.
- Provide automated migration helpers (PHP CS Fixer rules, Rector sets, etc.)
  when APIs undergo structural changes.

## Quality gates and examples

- `tests/Core/ApplicationModulesTest.php` in the Bamboo repository verifies
  middleware merging order and lifecycle sequencing.
- Example modules shipping with the framework (`Tests\Stubs\TestModuleAlpha`
  and `TestModuleBeta`) demonstrate how to publish middleware groups, global
  entries, and aliases in a deterministic manner.
- Modules are expected to integrate with the `composer validate:config` workflow
  by offering validators for any new configuration files and documenting the
  command in their README.

By adhering to this contract, module authors can publish integrations that
remain compatible across the entire 1.x series without surprises for operators.
# Module Extension API (v1.0 Freeze)

Modules let teams extend Bamboo's container, middleware pipeline, and background
workers. The guidance below defines what the v1.0 contract promises for module
authors and how framework consumers should expect modules to behave.

## Lifecycle hooks

`Bamboo\Core\Application::bootModules()` performs the module lifecycle in a
strict sequence to guarantee determinism across environments:

1. **Instantiation** – classes listed in `etc/modules.php` are instantiated in
   the order they appear. Each class **must** implement
   `Bamboo\Module\ModuleInterface` or the bootstrap will abort with an
   `InvalidArgumentException`.
2. **Register phase** – Bamboo calls `register(Application $app)` on each module
   before any `boot()` logic runs. Use this phase to:
   - bind services, singletons, and event listeners into the container;
   - publish configuration defaults or merge feature flags; and
   - perform work that can run multiple times without side effects. Prefer
     container helpers such as `$app->singleton()` to ensure idempotent
     bindings.
3. **Middleware contribution** – immediately after `register()` returns, the
   framework calls `middleware()` on the module and merges the returned arrays
   into the global configuration via `Config::mergeMiddleware()`. Modules should
   only return lists of class strings or aliases; they should never modify the
   configuration object directly.
4. **Boot phase** – once *every* module has registered, Bamboo calls
   `boot(Application $app)` on each instance in registration order. Because all
   modules are fully registered at this point, the boot phase is safe for
   resolving other modules' services, wiring middleware pipelines, and kicking
   off background workers.

### Timing guarantees and coordination

- Registration for all modules completes before any boot logic executes. If
  your module depends on another module's container bindings, defer that work to
  `boot()` or perform an `$app->has()` guard inside `register()`.
- Modules should not resolve the HTTP kernel or router during `register()`; the
  router is configured prior to module registration, and premature resolution can
  break route caching.
- Because modules are instantiated once per process, prefer lazy factories or
  closures for expensive resources and expose them via container bindings. Avoid
  storing state on the module instance itself.

### Middleware patterns

- Return middleware contributions as associative arrays with `global`,
  `groups`, and `aliases` keys to merge seamlessly with application
  configuration.
- Ensure middleware lists contain fully-qualified class strings. `Config` will
  normalise iterables and nested arrays, but empty strings or non-class values
  are discarded.
- When exposing middleware aliases, keep alias names unique across modules.
  Later modules in the list override earlier aliases, so coordinate with
  downstream applications before reusing common names (for example `auth` or
  `throttle`).

## Discovery and configuration

### Registering modules in `etc/modules.php`

`etc/modules.php` returns an ordered list of fully-qualified class names:

```php
<?php

use App\Modules\TelemetryModule;
use App\Modules\FeatureFlagsModule;

return [
    TelemetryModule::class,
    FeatureFlagsModule::class,
];
```

Key expectations:

- Declare only class-string literals. Conditional logic is acceptable, but keep
  the file synchronous and side-effect free (for example, gate a module behind
  an environment variable with `if (getenv('ENABLE_TELEMETRY')) { ... }`).
- Order matters. Modules later in the array can rely on container bindings or
  middleware aliases published by earlier entries.
- The file is autoloaded during bootstrap, so it must return quickly. Avoid
  network calls or heavy filesystem work.

### Module-owned configuration

- Modules may ship their own configuration files under `etc/`. Use a namespace
  that avoids collisions (for example, `etc/telemetry.php`).
- Configuration files should return arrays and follow the same dot-notation
  conventions as the core configuration.
- During `register()`, read configuration using `$app->config('telemetry.*')`.
  Do not attempt to load files manually.
- When publishing default configuration from a package, provide install
  instructions that copy the file into the host application's `etc/` directory
  and document which keys are required.

## Semantic versioning guidance

Modules must follow [Semantic Versioning 2.0.0](https://semver.org/) when
shipped as Composer packages or distributed internally:

- **MAJOR (x.0.0)** – increment when removing container bindings, renaming
  middleware aliases, changing the shape of published configuration arrays, or
  altering behaviour in a way that breaks existing consumers.
- **MINOR (x.y.0)** – increment when adding new services, configuration keys, or
  middleware that default to disabled/off. Optional parameters may be added to
  existing container factories, provided default values preserve the previous
  behaviour.
- **PATCH (x.y.z)** – increment for backwards-compatible bug fixes and internal
  cleanups.

Compatibility expectations:

- Declare the supported Bamboo versions in `composer.json` (for example,
  `"bamboo/framework": "^1.0"`).
- Maintain an automated compatibility suite that boots a minimal application,
  loads the module via `etc/modules.php`, and exercises its bindings. The
  canonical pattern uses `tests/Core/ApplicationModulesTest.php` as reference for
  asserting registration/boot order.
- Run your module's test suite against the lowest and highest supported Bamboo
  1.x versions before tagging a release.

## Deprecation policy

Bamboo enforces a two-step deprecation cycle for the module contract:

1. **Introduce deprecation in v1.x** – mark APIs, configuration keys, or
   middleware aliases as deprecated. Modules should:
   - emit an `E_USER_DEPRECATED` warning the first time the deprecated feature is
     used;
   - note the deprecation in the package `CHANGELOG.md` and release notes; and
   - document the replacement in `docs/modules.md` (or the module's README) with
     upgrade instructions.
2. **Removal no earlier than v2.0** – the deprecated surface remains available
   through the final v1.x release. Removal requires a major version bump and a
   migration guide that highlights breaking changes.

Communication requirements:

- Announce deprecations in the next release notes and the community discussion
  board.
- Provide copy-and-paste upgrade snippets for maintainers. A recommended format
  is:

  > **Deprecated:** `TelemetryModule::class` middleware alias `telemetry.trace`
  > will be removed in v2.0. Use `telemetry.telemetryTrace` instead and publish
  > the new configuration key `telemetry.trace.driver`.

- If the deprecation affects runtime behaviour, add logging or metrics to help
  operators identify affected services before the removal window closes.

## Quality gates

- Example modules used by the framework's own test suite live in
  `tests/Stubs/`. Use them as blueprints for structuring small, self-contained
  modules.
- Contract tests in `tests/Core/ApplicationModulesTest.php` guard lifecycle
  ordering and middleware merging. Run these when changing framework internals
  to ensure third-party modules remain compatible.
- Modules distributed to the community should enable static analysis (PHPStan at
  level 8 or higher) and code-style tooling to match the core project's quality
  bar.
