# Direct skills — implementation guide

This document complements the user-facing [README](../README.md) and ADRs **[009](adr/009-direct-skills-lifecycle-blocking-policy.md)**–**[012](adr/012-skills-content-hash-and-trust.md)**.

## Behaviour (ordered)

1. **Config + lock + hash** — `extra.ai-agent-skills` in root `composer.json`, generated **`composer.skills.lock`**, and a **`content-hash`** over the normalized sources subtree. **Trust is not part of the hash** (ADR-012).
2. **Resolver + fetch** — GitHub HTTPS/SSH URLs, `owner/repo` shorthand, GitHub **tree** URLs, and **local directories**. Generic non-GitHub `https://…git` remotes are **not** supported yet (CLI help matches this).
3. **Installer** — Materializes skills under **`install-dir`** (default `vendor/agent-skills/installed/`). **Git clone and scratch work** use **`cache-dir`** (default `vendor/agent-skills/cache`). `sources-dir` remains in config and in the hash for declared layout; it is not the clone root.
4. **Path safety** — `DirectSkillsPathGuard` rejects `..`, absolute paths, and NULs in config dirs and lock fields (`install-path`, `path`, path-type `url`). Resolved filesystem paths must stay under the project root before copy (mitigates tampered locks).
5. **Discovery + trust** — Direct installs appear in `list-skills` / `read-skill` with trust keys `direct:<source>/<skill-name>` stored in **`extra.ai-agent-skill.allow-skills`** (same mechanism as package skills). Use `composer skills:trust 'direct:…'`.
6. **CLI** — Dispatcher `composer skills …` and colon aliases `composer skills:add`, `skills:install`, `skills:update`, `skills:remove`, `skills:list` (ADR-010).
7. **Lifecycle** — `POST_INSTALL_CMD` applies **only** the lock (pinned). `POST_UPDATE_CMD` resolves floating refs, rewrites the lock, then installs. Lock mismatch / checksum failures **fail Composer** with a clear message. `COMPOSER_AGENT_SKILLS=0` disables direct-skill sync entirely.

## SKILL.md parsing

`SkillMarkdownParser` accepts **LF or CRLF** frontmatter fences. Name/description rules are shared with package skills via **`SkillFrontmatterValidator`** (length, control characters, bidi overrides) so AGENTS.md stays safe.

## Tests

- **Offline:** `tests/Unit/*`, `tests/Integration/DirectSkillsCoordinatorTest.php` (path sources only).
- **Network (`@group network`):** `tests/Integration/DirectSkillsNetworkTest.php` — real `git clone` from `github.com`. Skip locally or in CI with `SKIP_NETWORK_TESTS=1` (see workflow `vars.SKIP_NETWORK_TESTS`).
