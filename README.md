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

## Contributing
Interested in helping Bamboo grow? Fork the repository, review the contribution guidelines in the docs, and open a pull request with your improvements. We welcome bug fixes, new modules, and documentation updates.
