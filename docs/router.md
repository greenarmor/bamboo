# HTTP Router Contract (v1.0 Freeze Stub)

> **Status:** Outline only. Expand with authoritative behaviour notes and examples before finalising the v1.0.0 release candidate.

The router defines Bamboo's public HTTP surface. This stub captures the documentation tasks required to freeze the routing contract for the 1.x line.

## Supported HTTP methods and helpers

- [ ] Enumerate the verb helpers exposed by the router (e.g. `get`, `post`, grouped routes) and the fallback `match` API.
- [ ] Document URI token formats, parameter regex overrides, and default constraints.
- [ ] Provide examples that mirror canonical routes shipped in `routes/web.php`.

## Error handling behaviour

- [ ] Describe the JSON schema for default 404, 405, and 500 responses.
- [ ] Link to regression tests covering error payloads and middleware interactions.
- [ ] Capture logging expectations for error conditions (structured context, correlation IDs, etc.).

## Middleware ordering guarantees

- [ ] Explain how global, group, and per-route middleware stacks are composed.
- [ ] Record any reserved middleware slots and the extension points modules can hook into.
- [ ] Include diagrams or tables that clarify execution order.

## Deprecation policy

- [ ] Specify how helper removals emit `E_USER_DEPRECATED` notices.
- [ ] Detail the minimum support window for deprecated helpers (at least one minor release).
- [ ] Provide guidance for migration paths and automated codemods where possible.

## Contract validation

- [ ] Map documentation items to PHPUnit suites under `tests/Router/`.
- [ ] Track outstanding test coverage gaps that must be closed before the freeze.

