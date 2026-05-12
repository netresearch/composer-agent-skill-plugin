# ADR-010: CLI shape for direct skills (`composer skills …`)

**Status:** Accepted  
**Date:** 2026-05-11

## Context

The product spec targets UX such as:

```bash
composer skills add …
composer skills install
composer skills update
```

Existing plugin commands use **flat, hyphenated or colon names** ([`CommandCapability`](../../src/CommandCapability.php)): `list-skills`, `read-skill`, `skills:trust`, `skills:list-trust`. Composer’s console is Symfony Console: each **first token** after `composer` is the command name; there is no built-in generic `npm`-style subcommand tree unless we implement it.

## Decision

1. **Primary**: Register a single Symfony command whose **name is `skills`** and whose **first argument** is the subcommand (`add`, `install`, `update`, `remove`, `list`, `outdated`). Thus `composer skills add vercel-labs/skills` maps to command `skills` with arguments `add`, `vercel-labs/skills`, … — consistent with how Symfony resolves argv.

2. **Aliases (recommended in docs and registration)**: Also register colon-style commands mirroring existing conventions: `skills:add`, `skills:install`, `skills:update`, `skills:remove`, `skills:list`, `skills:outdated`, implemented either as thin wrappers delegating to the same dispatcher or as duplicate `BaseCommand` instances — whichever keeps duplication minimal.

3. **Trust commands**: Keep existing **`skills:trust`** and **`skills:list-trust`** as the stable surface for mutating and listing trust ([README](../../README.md)). Spec prose that says `composer skills trust …` should be interpreted as **documentation shorthand** for `composer skills:trust …` with appropriate `direct:` keys — **do not** introduce a second trust command under the `skills` dispatcher unless we later deprecate colon names with a major version.

4. **Verification**: After implementation, acceptance tests must assert both invocation styles where supported: `composer skills add …` and `composer skills:add …`.

## Consequences

**Positive**

- Matches user-facing spec examples without relying on unsupported Composer core features.
- Aligns with established plugin naming (`skills:*`).

**Negative**

- Spec writers must align terminology (`skills trust` vs `skills:trust`) to avoid duplicate CLI surfaces.

## Alternatives considered

1. **Only colon names** (`skills:add`) — simplest but diverges from spec examples.
2. **Multiple separate top-level commands** (`skills-add`) — pollutes `composer list` and duplicates options.
