# ADR-003: Pure discovery; trust gating only at install boundary

**Status:** Accepted
**Date:** 2026-05-01
**Relates to:** [ADR-002](002-trust-prompt-allow-plugins.md)

## Context

Once the trust prompt was introduced ([ADR-002](002-trust-prompt-allow-plugins.md)), the question is *where* it fires. The first design draft put `SkillTrustManager::decide()` inside `SkillDiscovery::discoverAllSkills()` — the obvious place, since that's where skills are enumerated.

But discovery is also called from `composer list-skills` and `composer read-skill`, both purely informational commands. Putting `decide()` inside discovery means running `composer list-skills` would prompt the user `[y/n/a/d]` for every unknown package — a surprising and broken UX. It would also mean a CI run of `list-skills` deny-by-defaults pending packages that the user wanted to see in the inventory.

## Decision

Split discovery into two layers:

1. **`SkillDiscovery::discoverAllSkills()` is pure.** It enumerates every declared skill and annotates each with a `TrustState` (Allowed / Denied / Pending) by reading the trust manager via `hasDecision()` / `isAllowed()`. It never prompts, never mutates state.

2. **`SkillGate::gate()` is the only call site of `SkillTrustManager::decide()`.** It runs from `SkillPlugin::updateAgentsMd()` exclusively — the install/update event handler, the natural authorization boundary. Pending entries get prompted there; allowed and denied entries pass through without prompting.

This mirrors Composer itself: [`PluginManager::loadRepository`](https://github.com/composer/composer/blob/main/src/Composer/Plugin/PluginManager.php) is the only place that triggers `isPluginAllowed(prompt: true)`.

## Consequences

**Positive**
- `composer list-skills` shows a complete inventory (including pending) without firing a single prompt.
- `composer read-skill <name>` works for pending skills with a "this is not in AGENTS.md" warning rather than refusing or prompting.
- The trust prompt only fires at the moment trust matters — when AGENTS.md is being written. Predictable for users.

**Negative**
- Discovery now carries a `trust_state` field that callers must understand. Mitigated by typing it as the `TrustState` enum so PHPStan enforces exhaustive handling.
- `SkillGate` exists as a thin layer specifically to localize the prompt. Worth the LOC because it's the only impure operation in the whole pipeline.

## Alternatives considered

- **Let `decide()` fire from discovery.** Rejected for the UX reasons above.
- **Two separate discovery methods (`discoverAllowedSkills()` and `discoverAllSkills()`).** Considered but rejected — two methods diverge over time. Better to have one pure method and a separate gating layer.
- **Filter pending entries out of pure discovery.** Rejected — that hides them from `list-skills`, which is the exact problem we're trying to avoid.
