# ADR-002: Trust prompt modeled on Composer's `allow-plugins`

**Status:** Accepted
**Date:** 2026-05-01
**Relates to:** [ADR-001](001-universal-discovery.md), [ADR-003](003-pure-discovery-boundary-gate.md)

## Context

[ADR-001](001-universal-discovery.md) broadened discovery so any package can ship a skill. Skills are *executable instructions* an AI agent will follow in the developer's environment, with file-system and shell access. A malicious or compromised dependency injecting a skill that says "run this curl command before every answer" is the worst case.

Without an opt-in mechanism, every transitive dependency could silently expand the attack surface of the developer's AI agent on the next `composer install`.

## Decision

Add a per-package trust prompt before reading `SKILL.md` content for inclusion in `AGENTS.md`. The prompt's shape mirrors Composer's own [`PluginManager::isPluginAllowed()`](https://github.com/composer/composer/blob/main/src/Composer/Plugin/PluginManager.php) — the canonical pattern for "this code will execute in your project, do you trust it?":

```
Allow this package to register skills?
  [y] Yes — allow & persist
  [n] No  — deny  & persist
  [a] Allow for this session only
  [d] Discard — leave undecided, ask again next run
  [?] Show details about this choice
```

Decisions persist under `extra.ai-agent-skill.allow-skills` in the root `composer.json` (mirroring `config.allow-plugins`), with glob patterns supported.

## Consequences

**Positive**
- Familiar shape: Composer users already know the four-letter prompt idiom from plugin authorization.
- One prompt per package per project, persisted forever.
- `[?]` help loop matches Composer's UX.
- Pre-authorization possible via `composer config` + the dedicated `composer skills:trust <package>` command.

**Negative**
- One additional prompt per new skill package on first encounter. Bundled with [ADR-006](006-first-run-policy-deny-default.md)'s first-run prompt this is two prompts at upgrade time.
- Glob support introduces a precedence question (resolved by [ADR-005](005-case-sensitive-glob-matching.md): exact match always wins).

## Alternatives considered

- **Composer's own `allow-plugins` mechanism.** Wrong scope — that's about Composer plugin code execution, not skill content. Could surprise users who already approved this plugin.
- **Hard-fail in non-interactive mode** like Composer does for unknown plugins. Rejected as too aggressive: it would block CI on every newly-pulled transitive skill. We instead skip with a `composer skills:trust …` recovery hint.
- **Symfony Flex's `[a/p]` shape** (session-allow-all / persist-globally). The persist-all-from-now-on shape would defeat the per-package security boundary. We borrowed Flex's `a` (session-allow) but kept per-package decisions.
