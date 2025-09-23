# Task Stub: Document the configuration deprecation policy

## Summary

Extend the v1.0 upgrade guide with a concise configuration deprecation policy so
operators understand the compatibility window for renamed keys, removed files,
and feature-flagged fallbacks. The configuration reference already outlines the
rules, but the upgrade playbook needs to repeat them in a digestible form.

## Background

- [`docs/roadmap/v1.0-prep.md`](../roadmap/v1.0-prep.md) leaves the "Write the
  configuration deprecation policy into the upgrade guide" checkbox unchecked.
- The detailed policy lives in
  [`docs/configuration/overview.md`](../configuration/overview.md#migration-and-deprecation-policy)
  and should be paraphrased for upgrade-focused readers.
- `docs/upgrade/v1.0.md` currently mentions deprecation handling at a high level
  but lacks explicit guidance for configuration changes.

## Definition of done

- [ ] The upgrade guide describes how long deprecated configuration keys remain
      supported, how shims are communicated, and when breaking removals occur.
- [ ] Examples illustrate a typical rename (legacy + new key) and the expected
      operator workflow for migrating.
- [ ] References back to the configuration overview ensure readers can deep-dive
      into the canonical tables if needed.

## Suggested implementation

1. Add a new subsection to `docs/upgrade/v1.0.md` under "Deprecations" or create
   a dedicated "Configuration deprecations" section.
2. Summarise the compatibility window (one minor release), the requirement for
   runtime notices, and the expectation to run `composer validate:config` during
   migrations.
3. Include a short code sample or bullet list showing how Bamboo reads both keys
   during the grace period.
4. Cross-link the more detailed reference material for readers who need schema
   tables or CLI commands.

## References

- Configuration policy source: [`docs/configuration/overview.md`](../configuration/overview.md#migration-and-deprecation-policy)
- Upgrade guide destination: [`docs/upgrade/v1.0.md`](../upgrade/v1.0.md)
- Roadmap tracker: [`docs/roadmap/v1.0-prep.md`](../roadmap/v1.0-prep.md)
# Task Stub: Document the configuration deprecation policy

## Summary

Extend the v1.0 upgrade guide with a concise configuration deprecation policy so
operators understand the compatibility window for renamed keys, removed files,
and feature-flagged fallbacks. The configuration reference already outlines the
rules, but the upgrade playbook needs to repeat them in a digestible form.

## Background

- [`docs/roadmap/v1.0-prep.md`](../roadmap/v1.0-prep.md) leaves the "Write the
  configuration deprecation policy into the upgrade guide" checkbox unchecked.
- The detailed policy lives in
  [`docs/configuration/overview.md`](../configuration/overview.md#migration-and-deprecation-policy)
  and should be paraphrased for upgrade-focused readers.
- `docs/upgrade/v1.0.md` currently mentions deprecation handling at a high level
  but lacks explicit guidance for configuration changes.

## Definition of done

- [ ] The upgrade guide describes how long deprecated configuration keys remain
      supported, how shims are communicated, and when breaking removals occur.
- [ ] Examples illustrate a typical rename (legacy + new key) and the expected
      operator workflow for migrating.
- [ ] References back to the configuration overview ensure readers can deep-dive
      into the canonical tables if needed.

## Suggested implementation

1. Add a new subsection to `docs/upgrade/v1.0.md` under "Deprecations" or create
   a dedicated "Configuration deprecations" section.
2. Summarise the compatibility window (one minor release), the requirement for
   runtime notices, and the expectation to run `composer validate:config` during
   migrations.
3. Include a short code sample or bullet list showing how Bamboo reads both keys
   during the grace period.
4. Cross-link the more detailed reference material for readers who need schema
   tables or CLI commands.

## References

- Configuration policy source: [`docs/configuration/overview.md`](../configuration/overview.md#migration-and-deprecation-policy)
- Upgrade guide destination: [`docs/upgrade/v1.0.md`](../upgrade/v1.0.md)
- Roadmap tracker: [`docs/roadmap/v1.0-prep.md`](../roadmap/v1.0-prep.md)
