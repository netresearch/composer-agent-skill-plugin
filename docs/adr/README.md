# Architecture Decision Records

Short documents capturing the load-bearing technical decisions in this plugin and why they were made. New ADRs go at the next free number. Superseded ADRs stay (don't delete history) but link forward to the replacement.

Format follows [MADR](https://adr.github.io/madr/) loosely — each ADR has *Context*, *Decision*, *Consequences*, and *Alternatives considered*.

| #   | Status   | Title                                                                                  |
| --- | -------- | -------------------------------------------------------------------------------------- |
| [001](001-universal-discovery.md) | Accepted | Universal discovery via `extra.ai-agent-skill`                                |
| [002](002-trust-prompt-allow-plugins.md) | Accepted | Trust prompt modeled on Composer's `allow-plugins`                            |
| [003](003-pure-discovery-boundary-gate.md) | Accepted | Pure discovery; trust gating only at install boundary                         |
| [004](004-atomic-write-flock-persistence.md) | Accepted | Atomic write + flock for trust persistence (not Composer's ConfigSource)      |
| [005](005-case-sensitive-glob-matching.md) | Accepted | Case-sensitive glob matching for trust patterns                               |
| [006](006-first-run-policy-deny-default.md) | Accepted | First-run policy `[n/d/a]` with deny-by-default                               |
| [007](007-auto-trust-root-package.md) | Accepted | Auto-trust the root package                                                   |
| [008](008-description-sanitization.md) | Accepted | Reject control chars and bidi overrides in skill descriptions                 |

## When to write a new ADR

- A reviewer asks "why didn't you do X instead?" and the answer is non-obvious from the code.
- A decision involves a tradeoff between security, UX, performance, or compatibility.
- Future maintainers will face the same question and need the rationale.
- A pivot was made mid-PR (e.g. ADR-006 replaces the original blanket-auto-seed approach).

Skip an ADR for: code-style choices, framework-imposed patterns, decisions that are evident from a single function's docblock.
