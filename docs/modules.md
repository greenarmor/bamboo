# Module Extension API (v1.0 Freeze Stub)

> **Status:** Stub only. Fill in authoritative guidance for module authors as part of the v1.0 contract freeze.

Modules let teams extend Bamboo's container, middleware pipeline, and background workers. Use this outline to track the required documentation updates before cutting v1.0.

## Lifecycle hooks

- [ ] Describe the responsibilities of `register`, `boot`, and any middleware registration hooks.
- [ ] Document timing guarantees relative to framework bootstrap and other modules.
- [ ] Highlight patterns for idempotent registration and configuration-driven behaviour.

## Discovery and configuration

- [ ] Explain how modules are registered in `etc/modules.php`.
- [ ] Provide examples of publishing services, configuration defaults, and middleware aliases.
- [ ] Capture expectations for module-specific configuration files under `etc/`.

## Semantic versioning guidance

- [ ] Define what constitutes a breaking change for the module interface.
- [ ] Clarify when optional methods or default parameters can be introduced without breaking consumers.
- [ ] Reference compatibility testing strategies for module maintainers.

## Deprecation policy

- [ ] Outline the two-step deprecation cycle (mark in v1.x, remove no earlier than v2.0).
- [ ] Document runtime warning requirements and CHANGELOG entries.
- [ ] Provide templates for announcing deprecations to downstream users.

## Quality gates

- [ ] Link to example modules and their test suites.
- [ ] Reference contract tests that guard the module lifecycle.
- [ ] Track outstanding tooling requirements (PHPStan extensions, PHP-CS-Fixer rules, etc.).

