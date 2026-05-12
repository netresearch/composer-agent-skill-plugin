# ADR-009: Direct skills lifecycle — lock policy vs. non-blocking Composer installs

**Status:** Accepted  
**Date:** 2026-05-11

## Context

Today [`SkillPlugin::updateAgentsMd`](../../src/SkillPlugin.php) wraps the entire install/update hook body in `catch (\Throwable)` so **no** plugin failure aborts `composer install` / `composer update` — including bugs in discovery, YAML parsing, or AGENTS.md generation.

The **Direct Skill Installation** product spec requires:

- `composer install` must apply **only** pinned state from `composer.skills.lock` (no implicit update).
- A **missing** or **stale** lock (relative to `extra.ai-agent-skills`) must fail with a defined exit code (e.g. `2`) during install flows — not silently continue.

Those requirements **contradict** a single blanket catch if direct-skill sync runs inside that block.

## Decision

1. **Split responsibilities** in the Composer script subscriber:
   - **`syncDirectSkills()`** (working name): validates `composer.skills.lock` against the normalized `extra.ai-agent-skills` content-hash, materializes `vendor/agent-skills/` from the lock, and performs checksum verification. **Throws** domain exceptions for policy violations (missing lock when sources exist, stale hash, checksum mismatch, unreachable pinned commit). These exceptions **must propagate** out of the event handler so Composer can fail the run with a non-zero exit code.
   - **`updateAgentsMd()`** (existing behaviour): discovers Composer-package skills, applies trust gating, regenerates `AGENTS.md`. **Keeps** the broad `catch (\Throwable)` so incidental failures (frontmatter bugs, IO errors on AGENTS.md) still **do not** block dependency installation.

2. **Ordering**: Run `syncDirectSkills()` **before** package discovery / AGENTS generation when direct sources are configured, so a lock failure short-circuits before unrelated work.

3. **Scope of “blocking”**: Only **explicit, classified** failures from the direct-skills sync path abort Composer. Accidental `TypeError`s inside that path still surface as fatal unless later wrapped — optional follow-up is an inner try/catch that maps unexpected errors to a single message while preserving exit failure.

## Consequences

**Positive**

- Aligns with the spec’s “install == reproducible lock” semantics without abandoning resilience for the legacy AGENTS.md path.
- CI can rely on “stale skills lock fails the build” when direct sources are declared.

**Negative**

- Two behavioural modes in one plugin (strict vs. lenient) — must be documented for maintainers.
- Requires discipline in code reviews: **never** wrap `syncDirectSkills()` in the same `catch (\Throwable)` as AGENTS generation.

## Alternatives considered

1. **Always lenient (warn only)** — rejects spec acceptance criteria for lock enforcement.
2. **Always strict (rethrow everything)** — restores the pre-security-review risk of random skill-plugin bugs breaking every `composer install`.
3. **Separate Composer plugin package** for direct skills — maximum isolation, unacceptable fork of maintenance.
