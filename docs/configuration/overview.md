# Configuration Schema Overview (v1.0 Contract Stub)

> **Status:** Skeleton only. Populate each section with definitive schema tables and validation guidance before the v1.0.0 release candidate.

Bamboo treats everything in `etc/` as part of the stable contract for self-hosted deployments. Use this document to coordinate the validation and documentation work required for the v1.0 configuration freeze.

## Documentation tasks

- [ ] Produce schema tables for every configuration entry point:
  - `etc/app.php`
  - `etc/cache.php`
  - `etc/database.php`
  - `etc/http.php`
  - `etc/metrics.php`
  - `etc/middleware.php`
  - `etc/modules.php`
  - `etc/redis.php`
  - `etc/server.php`
  - `etc/ws.php`
- [ ] Describe default values, allowed types, and environment variable overrides.
- [ ] Highlight cross-file dependencies (e.g. middleware referencing modules).

## Validation hooks

- [ ] Specify the shape of the `composer validate:config` script and where it lives.
- [ ] Outline runtime safeguards for missing or malformed configuration keys.
- [ ] Reference PHPUnit or integration tests that cover configuration loading.

## Migration and deprecation policy

- [ ] Document the process for renaming keys without breaking deployments.
- [ ] Provide examples of feature-flagged fallbacks and compatibility shims.
- [ ] Capture communication requirements for configuration migrations (release notes, upgrade guide, runtime notices).

