# ADR-012: `composer.skills.lock` content-hash — trust excluded

**Status:** Accepted  
**Date:** 2026-05-11

## Context

The Direct Skill spec introduces:

- `extra.ai-agent-skills` — declarative **sources** (intent).
- Optional `extra.ai-agent-skills.trust` — **operational** allow/deny style decisions for direct skills, analogous to `extra.ai-agent-skill.allow-skills` for Composer packages.

The lockfile `content-hash` must detect when **sources** (and paths/refs) drift from the lock. Trust toggles should **not** force a lock refresh every time a developer runs `composer skills:trust` / adjusts trust flags — that would churn `composer.skills.lock` and create meaningless merge conflicts.

## Decision

1. **Include in content-hash input** (normalized JSON of the relevant subtree):

   - `version`, `install-dir`, `sources-dir`, `cache-dir` (and similar structural keys).
   - The full `sources` array (names, types, URLs, refs, paths, skill selectors, `install-mode`).

2. **Exclude from content-hash input**:

   - **`trust`** (and any future sibling operational maps under `extra.ai-agent-skills`).
   - **`extra.ai-agent-skill`** entirely — including `allow-skills` — consistent with the spec’s note that Composer-package trust is separate; direct-skills hash must remain scoped to the **sources** subtree only.

3. **Normalization rules** (must be implemented exactly and tested):

   - Stable key ordering when serializing for hashing (e.g. recursive ksort).
   - Trim insignificant whitespace in JSON **before** hashing (not raw file bytes).

4. **Documentation**: State explicitly in user-facing docs that changing **trust** does **not** invalidate `content-hash`; changing **sources** does.

## Consequences

**Positive**

- Trust updates stay lightweight and don’t rewrite pinned commits unnecessarily.
- Clear separation: lock tracks **what** to fetch; composer.json trust maps track **whether** to expose.

**Negative**

- Two dimensions of “configuration drift” — developers must understand hash vs. trust independently.

## Alternatives considered

1. **Include trust in hash** — simple but noisy lock churn on every trust change.
2. **Separate `composer.skills.trust.lock`** — over-engineering for MVP.
