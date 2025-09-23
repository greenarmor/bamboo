# Starter Blueprint Hub (Stub)

> **Status:** Skeleton for the v1.0 documentation push. Fill in template instructions and scaffolding references before launch.

Bamboo will ship curated starter blueprints alongside the v1.0 release. Each blueprint should include a `composer create-project` command, configuration guidance, and operational notes. Use this document to coordinate authoring work.

## REST API starter

- [ ] Define the template repository or packaged archive.
- [ ] Document routing conventions, controller skeletons, and example tests.
- [ ] Capture recommended middleware and configuration defaults.

## Queue worker starter

- [ ] Document queue driver expectations and local development workflows.
- [ ] Provide sample job handlers and monitoring hooks.
- [ ] Outline deployment guidance for worker scaling.

## WebSocket gateway starter

- [ ] Describe handshake routes, authentication patterns, and broadcast helpers.
- [ ] Include state management guidance and failure handling strategies.
- [ ] Capture observability hooks (metrics, structured logs, health checks).

## Publishing checklist

- [ ] Add README excerpts to the main project `README.md` once the starters are ready.
- [ ] Verify MkDocs configuration surfaces each starter as a card/collection entry.
- [ ] Automate tests or smoke scripts that validate the create-project flows.

