# Bamboo (Bootstrapable Application Microframework Built for OpenSwoole Operations)

Bamboo is a lightweight PHP microframework that pairs OpenSwoole's asynchronous runtime with an Express-style developer experience. Use it when you need fast, event-driven services without the bulk of a monolithic stack.

## Prerequisites
- PHP 8.2 or newer with the OpenSwoole extension enabled
- Composer

Detailed setup guidance for macOS, Linux, and containerized environments lives in the [project documentation](docs/).

## Quick start
```bash
$ composer create-project greenarmor/bamboo example-app
$ cd example-app
$ php bin/bamboo http.serve
```
Then visit http://127.0.0.1:9501 to view the default application.

## Documentation
- Installation walkthroughs, CLI references, and upgrade notes are published in the [Docs directory](docs/).
- The latest rendered documentation is available on the [GitHub Pages site](https://greenarmor.github.io/bamboo/).

## UI templating and theming
Bamboo ships with a composable view layer driven by the `TemplateEngineManager`. The manager is registered in the default application provider and exposed from the service container under both its class name and the `view.engine` alias, so you can resolve it inside route handlers, controllers, or console commands without any manual wiring.【F:src/Provider/AppProvider.php†L6-L39】 It orchestrates one or more template engines that implement `TemplateEngineInterface` and renders structured templates into HTML by delegating to the configured driver.【F:src/Web/View/Engine/TemplateEngineManager.php†L7-L118】【F:src/Web/View/Engine/TemplateEngineInterface.php†L5-L10】

### Template configuration
Engine selection and per-page overrides live in `etc/view.php`. By default Bamboo maps the `components` engine name to the matching driver and uses it for every page unless a page-specific override is present.【F:etc/view.php†L1-L12】 Each page can opt into a different engine by setting `view.pages.<pageName>` to the engine name, or you can change the default engine globally through `view.default`.

You can register additional engines at runtime by calling `TemplateEngineManager::extend($driver, $factory)`. The factory receives the application instance and the resolved engine configuration array. Returning any `TemplateEngineInterface` implementation from the factory makes it available to templates referenced by the matching driver name. This is the recommended hook for integrating Blade, Twig, or bespoke renderers without modifying Bamboo’s core.【F:src/Web/View/Engine/TemplateEngineManager.php†L17-L76】

### Component-driven pages
The stock engine, `ComponentTemplateEngine`, expects templates expressed as nested PHP arrays that describe a `page` component and its children. The landing page ships with an example payload that drives both the API response and the prerendered HTML returned by the default HTTP route.【F:src/Web/View/LandingPageContent.php†L10-L134】 Supported components include `hero`, `feature-grid`, `stat-grid`, `faq`, `code-snippet`, and `footer`. Each component accepts a fixed set of keys (such as `title`, `items`, or `lines`) and the engine sanitizes every value before emitting HTML, so user-defined content can be passed safely through the renderer.【F:src/Web/View/Engine/Engines/ComponentTemplateEngine.php†L6-L348】

The component engine will silently skip unknown or empty nodes, allowing you to compose custom pages from only the sections you need. If you want to add an entirely new component type, extend `ComponentTemplateEngine` with your own renderer method and register it via a new driver, or swap `view.pages.landing` to point at a different engine that understands your domain-specific structures.

### Theming and styling
All markup emitted by the component engine is annotated with deterministic `bamboo-*` class names. The corresponding dark theme and layout primitives live in `public/assets/bamboo-ui.css`, covering hero layouts, cards, statistics, FAQ accordions, code snippets, and footer actions.【F:public/assets/bamboo-ui.css†L1-L346】 Overriding the look and feel is as simple as publishing your own stylesheet under the same path or enqueueing an additional asset from your HTTP layer. Because the renderer outputs semantic sections (`<section class="bamboo-hero">`, `<section class="bamboo-grid">`, `<footer class="bamboo-footer">`, etc.), you can also write incremental CSS modules that target specific blocks without forking the template definitions.

If you need dynamic theming at runtime (for example, per-tenant branding), generate the template array with different component values or swap to an engine that reads theme metadata from your configuration store. `TemplateEngineManager` can be resolved anywhere you have access to the application container, so you can build middleware or controllers that select engines and pass context-specific data just before rendering.【F:src/Web/View/Engine/TemplateEngineManager.php†L28-L76】

## Contributing
Interested in helping Bamboo grow? Fork the repository, review the contribution guidelines in the docs, and open a pull request with your improvements. We welcome bug fixes, new modules, and documentation updates.
