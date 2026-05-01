# ADR-007: Auto-trust the root package

**Status:** Accepted
**Date:** 2026-05-01

## Context

`Composer\InstalledVersions::getInstalledPackages()` includes the root package — the project itself. If the root project ships a `SKILL.md` (e.g., a sitepackage or library that wants to register its own skills), the discovery iteration picks it up the same as any dependency.

Without special handling, the trust prompt would then fire **on the user's own project**, asking them to authorize themselves. That's nonsensical UX and raises the question: who is "the user" deciding trust if the user *is* the package?

## Decision

`SkillTrustManager` accepts an optional `?string $rootPackageName` constructor argument. Before consulting the persisted rule map or session rules, `hasDecision()` and `isAllowed()` check `$packageName === $this->rootPackageName` and short-circuit to "trusted" if so. `decide()` returns `TrustState::Allowed` without writing anything to `composer.json`.

Production callers source the root name via `$composer->getPackage()->getName()` — both the install hook in `SkillPlugin::updateAgentsMd()` and the four CLI commands (via the `CommandContextTrait`). Tests can omit the argument; the manager then behaves as if no root were declared.

## Consequences

**Positive**
- A project that bundles its own SKILL.md "just works" — no prompt, no AGENTS.md noise about pending self-trust.
- The trust boundary still applies to every dependency (direct and transitive). Self-trust is the only exception.
- No persistence — root trust is computed in-memory each run. No rows added to `allow-skills`.

**Negative**
- One extra constructor argument that callers in tests have to remember to pass when testing root-trust behavior.
- Slight asymmetry: every other package needs an `allow-skills` entry; the root doesn't. Documented inline.

## Alternatives considered

- **Skip the root package during iteration.** Considered. Rejected because `composer list-skills` should still show the root's skill — just as allowed, not pending.
- **Always auto-allow when no name is configured.** Rejected — too permissive and surprising for tests that don't pass a root name; better to have explicit opt-in.
- **Compare on install path instead of name.** Considered — the architectural review verified that `InstalledVersions::getInstallPath($rootName)` returns the project root. Name-matching is cleaner and avoids realpath edge cases.
