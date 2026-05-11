# ADR-011: AGENTS.md schema — backward-compatible metadata for direct skills

**Status:** Accepted  
**Date:** 2026-05-11

## Context

[`AgentsMdGenerator`](../../src/AgentsMdGenerator.php) emits openskills-oriented XML with three fields per skill: `<name>`, `<description>`, `<location>`.

The Direct Skill spec proposes richer entries (`source`, `version`, `trust`, etc.). Strict downstream parsers may assume **only** those three tags exist; adding required new tags risks breaking consumers.

## Decision

1. **Mandatory compatibility set (unchanged)**  
   For every skill row emitted into `<available_skills>`, always include **exactly these three elements in order**: `<name>`, `<description>`, `<location>` — populated for both Composer-package skills and direct skills. Any consumer that only understands openskills v1 continues to work.

2. **Additive extension for direct skills**  
   After `<location>`, optionally emit **additional sibling elements** only when the skill is direct-installed, e.g. `<source>direct:owner/repo</source>`, `<pin>main@abc1234</pin>`, `<trust-state>pending|allowed|denied</trust-state>`.  
   - Omitted entirely for package-backed skills to minimize noise.  
   - Parsers that ignore unknown tags remain compatible; parsers that want trust gating can opt in.

3. **Schema versioning**  
   Add an optional attribute on the root `<skills_system>` element, e.g. `schema-version="2"`, when any entry includes extended tags — so tooling can detect richer output without heuristic sniffing.

4. **Security note**  
   **Do not** list **pending** or **denied** direct skills in the public index (spec §16; see also [PRD](../PRD.md) when updated); extended tags apply only to skills that are **eligible** for listing per trust policy (typically **allowed** only). The generator must filter before emitting.

5. **Documentation**  
   Update README when implementation lands: describe optional fields and point integrators at `schema-version`.

## Consequences

**Positive**

- Avoids a hard breaking change for openskills-compatible workflows.
- Allows agents that care about provenance to read structured metadata.

**Negative**

- Slightly larger AGENTS.md; optional follow-up is a sidecar JSON manifest if XML becomes unwieldy.

## Alternatives considered

1. **Replace XML with JSON block** — breaks existing openskills alignment.
2. **Embed metadata only in `composer skills list --json`** — satisfies tooling but not agent discoverability from AGENTS.md alone.
