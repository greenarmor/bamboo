# Task Stub: Integrate the configuration validator with CI

## Summary

Wire the `composer validate:config` script into the automated pipelines so
configuration regressions are caught during pull requests and nightly runs. The
script proxies to `php bin/bamboo config.validate`, and CI now runs it for every
matrix job to keep the validator active.

## Background

- [`docs/roadmap/v1.0-prep.md`](../roadmap/v1.0-prep.md) tracks this under the
  configuration schema checklist. The main checkbox and the "Land the Composer
  script and CI wiring" subtask are now complete.
- `composer.json` exposes the [`validate:config` script](../../composer.json)
  and `.github/workflows/ci.yml` runs it alongside the existing Composer QA
  targets.
- `src/Console/Command/ConfigValidate.php` implements the validator command and
  documents the expected exit codes.

## Definition of done

- [x] CI runs `composer validate:config` on every push and pull request for the
      PHP 8.2/8.3/8.4 matrix.
- [x] The README and contributor docs mention the validator in the local
      workflow section (if additional steps are required).
- [x] A failing configuration causes the CI job to exit non-zero with actionable
      logs.

## Suggested implementation

1. Add a dedicated step to `.github/workflows/ci.yml` after Composer installs to
   run `composer validate:config`.
2. Ensure the command uses the repository's Composer wrapper (`./bootstrap/shell-init.sh`)
   or updates PATH as needed.
3. If the validator produces artefacts (logs, reports), upload them on failure
   alongside existing PHPUnit caches.
4. Update `README.md` or other onboarding docs if the local workflow should now
   include this script by default.

## References

- Composer script definition: [`composer.json`](../../composer.json)
- CI pipeline: [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml)
- Command implementation: [`src/Console/Command/ConfigValidate.php`](../../src/Console/Command/ConfigValidate.php)
# Task Stub: Integrate the configuration validator with CI

## Summary

Wire the `composer validate:config` script into the automated pipelines so
configuration regressions are caught during pull requests and nightly runs. The
script proxies to `php bin/bamboo config.validate`, and CI now runs it for every
matrix job to keep the validator active.

## Background

- [`docs/roadmap/v1.0-prep.md`](../roadmap/v1.0-prep.md) tracks this under the
  configuration schema checklist. The main checkbox and the "Land the Composer
  script and CI wiring" subtask are now complete.
- `composer.json` exposes the [`validate:config` script](../../composer.json)
  and `.github/workflows/ci.yml` runs it alongside the existing Composer QA
  targets.
- `src/Console/Command/ConfigValidate.php` implements the validator command and
  documents the expected exit codes.

## Definition of done

- [x] CI runs `composer validate:config` on every push and pull request for the
      PHP 8.2/8.3/8.4 matrix.
- [x] The README and contributor docs mention the validator in the local
      workflow section (if additional steps are required).
- [x] A failing configuration causes the CI job to exit non-zero with actionable
      logs.

## Suggested implementation

1. Add a dedicated step to `.github/workflows/ci.yml` after Composer installs to
   run `composer validate:config`.
2. Ensure the command uses the repository's Composer wrapper (`./bootstrap/shell-init.sh`)
   or updates PATH as needed.
3. If the validator produces artefacts (logs, reports), upload them on failure
   alongside existing PHPUnit caches.
4. Update `README.md` or other onboarding docs if the local workflow should now
   include this script by default.

## References

- Composer script definition: [`composer.json`](../../composer.json)
- CI pipeline: [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml)
- Command implementation: [`src/Console/Command/ConfigValidate.php`](../../src/Console/Command/ConfigValidate.php)
