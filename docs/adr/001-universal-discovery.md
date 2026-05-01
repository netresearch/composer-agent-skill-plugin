# ADR-001: Universal discovery via `extra.ai-agent-skill`

**Status:** Accepted
**Date:** 2026-05-01
**Deciders:** PR [#43](https://github.com/netresearch/composer-agent-skill-plugin/pull/43), issue [#42](https://github.com/netresearch/composer-agent-skill-plugin/issues/42)

## Context

Pre-v0.2.0 the plugin only discovered packages whose `composer.json` declared `type: ai-agent-skill`. A package's `type` is its primary identity (`library`, `symfony-bundle`, `typo3-cms-extension`, …) — there's no second slot. Maintainers who wanted to ship a skill alongside an existing library had to publish a separate companion package, doubling the maintenance surface.

[Issue #42](https://github.com/netresearch/composer-agent-skill-plugin/issues/42) asked us to mirror the pattern [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer) uses: any package opts in via `extra.phpstan.includes`, regardless of `type`. [TanStack Intent](https://tanstack.com/intent/latest) ships the same idea in the npm world.

## Decision

Discovery iterates **all** installed packages and picks those that either:

1. Declare `extra.ai-agent-skill` in their `composer.json` (the new primary path), **or**
2. Carry the legacy `type: ai-agent-skill` (preserved as backward compatibility).

`SkillDiscovery::declaresSkills()` on `PackageInfo` encodes the rule.

## Consequences

**Positive**
- A library can ship a single skill alongside its primary purpose without a companion package.
- Aligns with established Composer extensions (phpstan, drupal-scaffold) — familiar to plugin authors.
- `type: ai-agent-skill` packages keep working unchanged.

**Negative**
- The discovery surface broadened from "deliberately published skill packages" to "any package can declare a skill". Any transitive dependency could in principle inject one. Mitigated by [ADR-002](002-trust-prompt-allow-plugins.md) (per-package trust prompt) and [ADR-006](006-first-run-policy-deny-default.md) (deny-by-default first-run policy).
- The `extra.ai-agent-skill` key now does double duty (path declaration in deps, allow-skills sub-key in root). See `TrustStore::mergeWithExisting` for the migration logic.

## Alternatives considered

- **Keep the `type` requirement.** Rejected because it forces companion packages — exactly the duplication issue #42 calls out.
- **Use a brand-new key like `extra.ai-skills`.** Rejected because the existing `extra.ai-agent-skill` key was already documented and used; renaming would break backward compatibility for marginal aesthetic gain.
- **Auto-detect SKILL.md presence in any package.** Rejected — too aggressive (drive-by skill exposure), and mismatches the Composer ecosystem norm of explicit `extra.*` opt-in.
