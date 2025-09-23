# HTTP Router Contract (v1.0 Freeze)

Bamboo routes requests through `Bamboo\Core\Router`, a thin wrapper around
FastRoute tuned for long-lived OpenSwoole workers. The router API is part of the
v1.0 public contract; helpers, middleware semantics, and error payloads will
remain stable across the 1.x series.

## Route registration and helpers

Applications and modules obtain the router from the container (`$app->get('router')`)
or inject it into service providers. Routes can be registered at bootstrap time
or cached to disk.

```php
$router->get('/hello/{name}', [GreetingController::class, 'show']);
$router->post('/jobs', RouteDefinition::forHandler([
    JobController::class, 'enqueue'],
    middlewareGroups: ['queue'],
));
$router->addRoute('DELETE', '/cache/{key}', function (ServerRequestInterface $request, array $vars) {
    return new JsonResponse(['deleted' => $vars['key']]);
});
```

### Supported methods

- `Router::get(string $path, callable|array|RouteDefinition $handler, array $middleware = [], array $groups = [])`
- `Router::post(...)`
- `Router::addRoute(string $method, string $path, ...)`

`addRoute` accepts any valid HTTP verb and stores the definition internally with
a normalised signature. Custom helpers can wrap `addRoute` when additional verbs
(such as PUT, PATCH, DELETE) are required.

### Route definitions

Handlers can be expressed as:

- A PHP callable (`function (...) { ... }`).
- A controller tuple `[ClassName::class, 'method']`.
- An associative array with `handler`, `middleware`, `middleware_groups`, or
  legacy keys (`uses`, `action`).
- A `Bamboo\Core\RouteDefinition` instance for explicit signatures.

`RouteDefinition::signature` overrides the derived signature used for cache keys
and request context metadata. When omitted, the router uses `"<METHOD> <PATH>"`.

### Parameter tokens

Paths follow FastRoute syntax. `{name}` captures a single path segment using the
`[^/]+` regex. Inline constraints are supported: `{id:\d+}` restricts the token
to digits, `{slug:[a-z0-9\-]+}` enforces slug-friendly characters. Matched
variables are passed to the handler as part of the `$vars` array or injected via
argument names (`$id`, `$slug`) when the callable signature includes them.

Closures and controller methods may type-hint the PSR-7 request or the
application container; the router performs best-effort argument matching. The
following invocation strategies are part of the contract:

```php
$router->get('/demo/{id}', function (ServerRequestInterface $request, array $vars, Application $app) {
    // $request === the incoming request
    // $vars === ['id' => '123']
    // $app   === Bamboo\Core\Application instance
});
```

## Middleware composition

Middleware ordering is deterministic:

1. **Global middleware** – `etc/middleware.php['global']`.
2. **Group middleware** – merged for each named group listed by the route.
3. **Route middleware** – entries supplied directly to the route definition.

Aliases declared under `etc/middleware.php['aliases']` are resolved before the
pipeline executes. Modules may contribute additional entries via
`ModuleInterface::middleware`; contributions are merged with the same ordering
rules. See `tests/Core/ApplicationPipelineTest.php` for a thorough ordering
specification and terminable middleware guarantees.

## Error handling

The router produces canonical JSON payloads for framework-level errors:

- **404 Not Found** – `{"error":"Not Found"}`
- **405 Method Not Allowed** – `{"error":"Method Not Allowed"}` with an
  `Allow` header listing permitted verbs.
- **500 Routing failure** – `{"error":"Routing failure"}` emitted when an
  unexpected dispatcher status occurs.

These responses inherit headers required for Prometheus and tracing middleware,
ensuring monitoring remains consistent even when no route matches. Application
middleware can override or extend the behaviour by catching responses upstream.

## Route caching contract

`Router::cacheTo($file)` exports the in-memory map to PHP, enabling instant boot
without re-registering routes. Because PHP cannot serialise closures safely, the
cache routine rejects any definition that contains a closure and throws a
`RuntimeException`. Operators should run `php bin/bamboo routes.cache` as part of
build pipelines once the application route table stabilises.

## Deprecation policy

- Existing helper methods (`get`, `post`, `addRoute`, `gatherMiddleware`) remain
  stable throughout the 1.x line. New helpers may be added in minor releases.
- Future helper deprecations must trigger `E_USER_DEPRECATED` notices while
  keeping the old API functional for at least one minor release.
- Parameter token semantics and the default error payload schema are frozen; any
  changes require a new major release with upgrade-guide choreography.

## Contract validation

- `tests/Http/ApplicationRoutesTest.php` exercises built-in routes, parameter
  injection, and the Prometheus `/metrics` exporter.
- `tests/Core/ApplicationPipelineTest.php` covers middleware ordering, alias
  resolution, and terminable middleware behaviour.
- `tests/Roadmap/V0_4/TimeoutMiddlewareTest.php` and
  `tests/Roadmap/V0_4/CircuitBreakerMiddlewareTest.php` ensure resilience
  middleware integrates correctly with router-driven signatures.
- `tests/Console/RoutesShowCommandTest.php` and `tests/Core/ApplicationModulesTest.php`
  guard the cached route format and module contributions.

Together with the new `config.validate` command, these suites guarantee the
router contract is exercised in CI before the v1.0 release is tagged.
# HTTP Router Contract (v1.0 Freeze)

Bamboo's HTTP router exposes the framework's primary public surface area. This
reference freezes the v1.x contract so that applications can rely on routing,
middleware, and error semantics remaining stable across the major series.

## Supported HTTP methods and helpers

### Registration APIs

`Bamboo\Core\Router` normalises every route into an internal map keyed by the
uppercased HTTP method and URI template.【F:src/Core/Router.php†L11-L148】  Routes
can be registered through the following helpers:

- `addRoute($method, $path, $action, array $middleware = [], array
  $middlewareGroups = [])` is the generic entry-point. It accepts any RFC 7231
  token (e.g. `PUT`, `PATCH`, `DELETE`, `OPTIONS`) and should be used for verbs
  other than GET/POST.【F:src/Core/Router.php†L14-L31】
- `get($path, $action, array $middleware = [], array $middlewareGroups = [])`
  and `post(...)` delegate to `addRoute()` with a fixed method string.【F:src/Core/Router.php†L19-L25】
- `RouteDefinition::forHandler(...)` is an ergonomic factory when you need to
  bundle middleware, middleware groups, or a custom signature with the handler
  metadata.【F:src/Core/RouteDefinition.php†L4-L19】

Handlers may be:

- A callable (closure, invokable object, or function name).
- A `[ControllerClass::class, 'method']` pair; the router will instantiate the
  controller, pass it the current `Application`, and invoke the method.【F:src/Core/Router.php†L101-L108】
- An associative array with a `handler` key plus optional `middleware`,
  `middleware_groups`/`middlewareGroups`/`groups`, and `signature` overrides; the
  router normalises these shapes so downstream code receives a consistent
  structure.【F:src/Core/Router.php†L120-L148】

The dispatcher reflects handler arguments and automatically injects the PSR-7
request, the route variable array, and the running `Application` instance when a
parameter's type-hint or name matches one of those services.【F:src/Core/Router.php†L167-L239】

### Middleware attachments

Each route may supply inline middleware aliases and middleware groups. At
normalisation time the router flattens these lists, merges any values provided by
`RouteDefinition` or associative array metadata, and stores them separately so
they can be combined with the configured global and group stacks during
request handling.【F:src/Core/Router.php†L120-L148】【F:src/Core/Application.php†L42-L57】

### Parameter tokens and URI patterns

Bamboo delegates path compilation and matching to FastRoute, so URI templates use
FastRoute's token syntax. The router passes method/path pairs directly to
`FastRoute\RouteCollector`, which means:

- Curly-brace placeholders capture single path segments (e.g.
  `/hello/{name}` binds a `name` variable).【F:routes/http.php†L25-L30】【F:src/Core/Router.php†L63-L114】
- Placeholders may specify a custom regular expression using the
  `{token:pattern}` form. Because Bamboo does not alter the definition before it
  reaches FastRoute, the framework inherits FastRoute's default of `[^/]+` for
  unspecified patterns.【F:src/Core/Router.php†L63-L83】
- Optional segments and nested groups follow FastRoute's square-bracket syntax.
  Since definitions are forwarded verbatim, any template supported by
  FastRoute's parser is accepted by Bamboo.【F:src/Core/Router.php†L63-L83】

### Canonical route examples

The default application registers routes using the helpers described above. These
examples double as integration tests for the router contract:

```php
$router->get('/metrics', RouteDefinition::forHandler([
    Bamboo\Web\Controller\MetricsController::class, 'index'],
));

$router->get('/hello/{name}', RouteDefinition::forHandler(
    function ($request, $vars) {
        return new Nyholm\Psr7\Response(200, ['Content-Type' => 'text/plain'], "Hello, {$vars['name']}!\n");
    },
    middleware: [Bamboo\Web\Middleware\SignatureHeader::class],
));

$router->post('/api/echo', function ($request) {
    $body = (string) $request->getBody();
    return new Nyholm\Psr7\Response(200, ['Content-Type' => 'application/json'], $body ?: '{}');
}, [Bamboo\Web\Middleware\SignatureHeader::class]);
```
【F:routes/http.php†L8-L65】

## Error handling behaviour

### Dispatcher outcomes

`Router::match()` wraps FastRoute's dispatcher and returns a structured array
containing the outcome, allowed methods (for 405 responses), and extracted route
variables. This structure feeds directly into the HTTP response generator used by
`Application::handle()`.【F:src/Core/Router.php†L63-L117】【F:src/Core/Application.php†L37-L83】

### JSON responses

The router standardises three error payloads when a request cannot be fulfilled
by application code:

| Status | Body | Notes |
| ------ | ---- | ----- |
| 404 | `{ "error": "Not Found" }` | Returned when no route matches the URI.【F:src/Core/Router.php†L95-L99】 |
| 405 | `{ "error": "Method Not Allowed" }` | Returned when the URI exists but does not accept the request method. The `match()` result still includes an `allowed` list for callers that need it.【F:src/Core/Router.php†L74-L115】 |
| 500 | `{ "error": "Routing failure" }` | Defensive fallback if FastRoute returns an unknown status code.【F:src/Core/Router.php†L95-L118】 |

Each response is emitted with a `Content-Type: application/json` header. Because
routing happens inside the middleware pipeline, global middleware such as the
request ID generator still run and attach correlation headers even for 4xx/5xx
responses.【F:src/Core/Application.php†L55-L83】【F:src/Web/Middleware/RequestId.php†L7-L22】

### Logging and correlation expectations

The request ID middleware stores the generated or propagated identifier, HTTP
method, and route signature in `RequestContext`. That context is merged into every
Monolog record so operators receive correlation IDs alongside error reports and
404/405 traces.【F:src/Web/Middleware/RequestId.php†L7-L22】【F:src/Core/Application.php†L31-L52】【F:src/Provider/AppProvider.php†L10-L27】
Regression tests assert both header propagation and structured logging metadata
for matched and unmatched routes.【F:tests/Http/RequestIdMiddlewareTest.php†L31-L85】

### Regression coverage

- `ApplicationPipelineTest::testUnmatchedRoutesShareKernelCacheEntry` exercises
  repeated 404 responses and ensures the middleware stack executes with the
  expected context for missing routes.【F:tests/Core/ApplicationPipelineTest.php†L230-L263】
- The request ID middleware tests listed above confirm correlation IDs surface in
  responses and logs, covering the observable contract for error conditions.

## Middleware ordering guarantees

### Pipeline composition

The application bootstraps a `Kernel` that expands middleware aliases and groups
from configuration. For each request it concatenates the fully-expanded global
stack with route-provided middleware (aliases and groups are recursively
resolved). The resulting list is cached per-route signature so subsequent
requests reuse the same ordering.【F:src/Core/Application.php†L42-L83】【F:src/Web/Kernel.php†L6-L123】

Middleware are invoked in the order produced by the kernel, while "after" logic
runs in the reverse order as the stack unwinds. Terminable middleware are queued
and executed after a response is produced, preserving the same request instance
that reached the termination hook.【F:src/Core/Application.php†L55-L83】

### Deterministic ordering guarantees

- Global middleware always execute first, in declaration order, followed by
  expanded middleware groups, then route-scoped middleware aliases.【F:src/Web/Kernel.php†L21-L123】
- Group references and aliases are flattened depth-first. Circular references are
  detected and rejected to avoid infinite recursion.【F:src/Web/Kernel.php†L81-L107】
- The kernel invalidates its cache when the middleware configuration or the route
  cache file timestamp changes, ensuring stale stacks are never reused across
  deployments.【F:src/Web/Kernel.php†L36-L54】

### Regression coverage

`tests/Core/ApplicationPipelineTest.php` captures the execution order contract:

- `testMiddlewarePipelineResolvesConfiguredOrder` asserts the global → group →
  route ordering, header mutations, and middleware cache reuse.【F:tests/Core/ApplicationPipelineTest.php†L180-L228】
- `testKernelCacheInvalidatesWhenConfigurationChanges` and
  `testKernelCacheInvalidatesWhenRouteCacheTimestampChanges` ensure the kernel's
  cache invalidation semantics stay in sync with configuration updates and route
  cache refreshes.【F:tests/Core/ApplicationPipelineTest.php†L265-L319】
- `testKernelCachesSingleEntryForUnmatchedRoutes` verifies unmatched requests
  share a `__global__` cache entry while still recording the active route in the
  request context.【F:tests/Core/ApplicationPipelineTest.php†L321-L361】
- `testTerminableMiddlewareRunsAfterResponseIsProduced` exercises the termination
  hooks and guarantees the recorded events and response headers appear in the
  expected order.【F:tests/Core/ApplicationPipelineTest.php†L364-L404】

## Deprecation policy

Router-facing helpers (`addRoute`, verb-specific helpers, `RouteDefinition`
constructors, and middleware utilities) follow Bamboo's deprecation rules:

1. Deprecations must trigger `E_USER_DEPRECATED` notices via `trigger_error()` as
   soon as an alternative exists. Notices include the planned removal version and
   migration guidance.
2. Deprecated helpers remain available for at least one subsequent minor release
   (e.g. deprecated in v1.2, removed no earlier than v1.3) so applications have a
   full release cycle to migrate.
3. Documentation and upgrade notes describe migration strategies, including
   suggested codemod patterns when a mechanical transformation exists (such as
   replacing `post()` with `addRoute('POST', ...)`).

## Contract validation

The following regression suites cover the router contract:

| Concern | Test coverage |
| ------- | ------------- |
| Route caching and closure safeguards | `tests/Core/RouterCacheTest.php`【F:tests/Core/RouterCacheTest.php†L20-L91】 |
| Middleware ordering, caching, and terminators | `tests/Core/ApplicationPipelineTest.php`【F:tests/Core/ApplicationPipelineTest.php†L180-L404】 |
| Request ID propagation and logging context | `tests/Http/RequestIdMiddlewareTest.php`【F:tests/Http/RequestIdMiddlewareTest.php†L31-L85】 |
| Default application routes | `routes/http.php` (serves as the canonical example suite)【F:routes/http.php†L8-L65】 |

Future router-specific PHPUnit namespaces will live under `tests/Router/`; until
then the existing suites above serve as the authoritative regression coverage for
this contract.
