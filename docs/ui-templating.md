# UI templating and theming

Bamboo ships with a composable frontend rendering system designed for
OpenSwoole workloads where responses should be generated without
blocking the event loop. The pieces described below live in
`src/Web/View` and are registered by the default application provider, so
any HTTP handler, CLI command, or background task can render templates in
exactly the same way.

## TemplateEngineManager lifecycle

`TemplateEngineManager` is the single entry point for view rendering. It
is registered as a singleton by `AppProvider` under both its class name
and the `view.engine` alias, which means you can resolve it from the
container using either identifier without performing any manual wiring.

```php
$engine = $app->make(TemplateEngineManager::class);
$engine = $app->make('view.engine'); // equivalent
```

The manager exposes two primary responsibilities:

1. **Driver resolution.** It loads configuration from `etc/view.php`,
   selects the default driver, and maps page-specific overrides to the
   correct engine instance.
2. **Driver extension.** You can register new template engines at runtime
   via `TemplateEngineManager::extend($name, $factory)`. The factory
   receives the running `Application` instance plus the driver-specific
   configuration array and must return an implementation of
   `TemplateEngineInterface`.

This design allows you to add Blade, Twig, or any bespoke templating
system without touching Bamboo’s core. Because the manager itself is a
plain service, you can register engines inside service providers, module
boot hooks, or even request middleware.

## Configuration reference

All view settings live in `etc/view.php`. The default file ships with the
following structure:

```php
return [
    'default' => 'components',
    'pages' => [
        'landing' => 'components',
    ],
    'drivers' => [
        'components' => [
            'engine' => ComponentTemplateEngine::class,
        ],
    ],
];
```

Key behaviours to be aware of:

- `default` controls which engine renders any page that does not have a
  page-specific override.
- `pages` maps logical page names (e.g. `landing`, `status`, `docs`) to a
  specific driver. Names are arbitrary—you can add new entries and point
  them at any registered driver.
- `drivers` is a dictionary of driver names to configuration arrays. Each
  entry must contain an `engine` class name, and the remaining keys are
  passed directly to the engine factory.

When you call `$manager->render('landing', $context)`, Bamboo will:

1. Look for a `landing` override inside `view.pages` and fall back to the
   default driver if none is set.
2. Resolve that driver from the `view.drivers` map.
3. Instantiate the engine (lazily) and cache it for future renders.
4. Pass the provided `$context` array to the engine’s `render` method.

## Component template engine

`ComponentTemplateEngine` is the stock renderer that powers Bamboo’s
landing page. It expects structured PHP arrays that describe a hierarchy
of components. The top-level array contains a `page` key with component
metadata and a `layout` section that lists the child components to render
in order. Each component is responsible for its own HTML output, and the
engine sanitizes all data before it reaches the browser.

Supported components include (but are not limited to):

- `hero`
- `feature-grid`
- `stat-grid`
- `faq`
- `code-snippet`
- `footer`

Each component understands a dedicated schema. For example, `hero`
expects `title`, `subtitle`, `actions`, and `image` keys, while
`code-snippet` looks for `language`, `title`, and `lines`. Empty nodes or
unknown components are skipped gracefully so you can compose pages from
only the sections you need.

To add a brand-new component type:

1. Extend `ComponentTemplateEngine` and implement a renderer method (for
   example, `renderTestimonials(array $component): string`).
2. Register your subclass as a new driver name under `view.drivers`.
3. Point the appropriate page (or the global default) at the new driver.

Because engines are registered lazily, this approach lets you ship custom
components as part of a module without forking the core renderer.

## Theming hooks

All markup emitted by the component engine uses deterministic `bamboo-*`
CSS class names so that stylesheets can be swapped without editing the
PHP templates. The bundled styles live in
`public/assets/bamboo-ui.css` and cover layout primitives, typography,
links, buttons, grids, FAQ accordions, statistic cards, and code blocks.

Common customization strategies include:

- **Override the stylesheet.** Publish your own CSS file at
  `public/assets/bamboo-ui.css` (or configure your HTTP layer to serve a
  different asset) to take full control over every component.
- **Layer incremental styles.** Keep the stock stylesheet and enqueue an
  additional file that targets the `bamboo-*` selectors you want to
  modify, such as `.bamboo-hero` or `.bamboo-footer`.
- **Inject per-tenant themes.** Generate CSS variables or inline styles at
  runtime before calling `TemplateEngineManager::render()` so each tenant
  receives branded colors and assets.

Because the renderer outputs semantic `<section>` and `<footer>` blocks,
standard CSS techniques like prefers-color-scheme media queries and CSS
modules work without modification.

## Extending beyond components

If your application uses a different templating paradigm entirely,
register a new engine driver. Each engine only needs to implement:

```php
interface TemplateEngineInterface
{
    public function render(string $template, array $context = []): string;
}
```

The `$template` parameter is the logical name you pass to
`TemplateEngineManager::render()`. The meaning of that name is entirely
up to the engine—treat it as a filename, a database key, or a compiled
handle.

When integrating third-party systems, keep these guidelines in mind:

- Engines should be stateless; cache expensive resources (compiled
  templates, filesystem watchers) across renders when possible.
- Avoid blocking I/O inside engine constructors when running under
  OpenSwoole. Perform setup lazily during the first `render()` call.
- Document the expected configuration keys so downstream applications can
  opt into your driver confidently.

With these hooks you can progressively enhance Bamboo’s default frontend
or replace it wholesale while still benefiting from the framework’s
asynchronous core.
