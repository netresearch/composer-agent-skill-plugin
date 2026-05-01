# ADR-006: First-run policy `[n/d/a]` with deny-by-default

**Status:** Accepted (replaces an earlier draft within the same PR)
**Date:** 2026-05-01

## Context

Existing v0.1.x users could have any number of `type: ai-agent-skill` packages already installed. The naive upgrade path would prompt the user `[y/n/a/d]` for every one of them on first install — a re-prompt avalanche.

The first draft of this PR auto-seeded every legacy package as trusted, reasoning that "the user already chose to `composer require` them — implicit trust". The security review flagged this HIGH: a transitive dependency the user *never knowingly chose* (pulled in by some other package they did require) inherits trust by default. The supply-chain attack window is exactly the gap between `require` and review.

## Decision

When `extra.ai-agent-skill.allow-skills` is absent and there are installed `type: ai-agent-skill` packages, fire a single one-time prompt:

```
The AI Agent Skill plugin found N existing skill packages that have not been authorized yet.
  - vendor/skill-a   (in your require)
  - vendor/skill-b   (pulled in by another package)
How should they be trusted on this first run?
  [n] None — prompt for each package later (default, strict)
  [d] Direct dependencies only — auto-trust packages your root composer.json explicitly requires
  [a] All — auto-trust every existing skill package (including transitive)
(defaults to n) [n,d,a]:
```

- **Default `n` (strict)** — every package goes through the per-package prompt during the same install.
- **`d`** — auto-trust only direct deps from the root's `require`/`require-dev`.
- **`a`** — restore v0.1.x behaviour at the user's explicit consent.

In non-interactive mode (CI), default to `n` and emit a `composer skills:trust …` recovery hint per affected package (marked direct/transitive).

The map is always written (possibly empty `{}`) so the prompt fires at most once per project. The `TrustStore::allowSkillsExists()` check reads "map present, even if empty" — that's the marker.

## Consequences

**Positive**
- Default deny preserves the security boundary the trust prompt is supposed to provide.
- `[a]` keeps the v0.1.x UX one keystroke away for users who knowingly want it.
- `[d]` distinguishes "I require this" from "something pulled it in", which is exactly the security model we want.
- One prompt total at upgrade time (not N).

**Negative**
- The prompt is two-deep ([Composer's `allow-plugins` prompt for our plugin] → [our first-run-policy prompt]). Per-package prompts come third for users who pick `n` or `d`. Documented in README.
- Users on shared/CI environments need to make the policy decision before running CI — same as Composer's `allow-plugins`.
- The `[d]` option requires reading `getRequires()` + `getDevRequires()` from the root package, which adds coupling between `SkillPlugin` and `Composer\Package\RootPackageInterface`. Acceptable — that's a stable Composer API.

## Alternatives considered

- **Auto-seed everything (the rejected first draft).** Security HIGH. Replaces the trust boundary with a blanket trust step.
- **Auto-seed direct deps only, by default.** Considered. Rejected because users on shared CI may have packages they don't recognize even in their direct requires (e.g., a build script auto-added them). Better to ask.
- **Don't seed at all; prompt for everything always.** Considered. Rejected because for users with 10+ legacy skill packages this is the avalanche we set out to avoid. The `[a]` opt-in solves it without compromising default safety.
- **Auto-seed only on detected upgrade (some "previous version was installed" marker).** Rejected — there's no reliable way to detect "previously had v0.1.x" vs "fresh install with skill packages" from a single composer state. Treating both the same is simpler and safer.
