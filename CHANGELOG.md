# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Direct skill installation** — declare sources under `extra.ai-agent-skills`, pin them in **`composer.skills.lock`**, and materialize trees under `install-dir` (default `vendor/agent-skills/installed/`). CLI: **`composer skills`** dispatcher with **`skills:add`**, `skills:install`, `skills:update`, `skills:remove`, `skills:list` (see README, `docs/IMPLEMENTATION-DIRECT-SKILLS.md`, ADRs 009–012).
- **Git cache directory** — ephemeral clones and worktrees use **`cache-dir`** (default `vendor/agent-skills/cache`). Config keys **`install-dir`**, **`sources-dir`**, and **`cache-dir`** plus lock path fields are validated (no `..`, no absolutes) so installs cannot escape the project root on a tampered lock.
- **Shared trust store for direct skills** — allow/deny uses **`extra.ai-agent-skill.allow-skills`** with keys `direct:<source>/<skill-name>`; `composer skills:trust` / `list-skills` / `read-skill` behave like package skills.
- **`DiscoveredSkills`** helper — merges package + direct discovery for `list-skills` / `read-skill` and prints a **`[NOTE]`** when the same skill name appears twice (package entry wins).
- **`FilesystemUtil`** — `0755` directory mode, shared recursive tree removal with optional verbose IO diagnostics (replaces duplicated `rmTree` / `0777` usage).

### Changed

### Fixed

## [2.0.0] - 2026-05-01

### Breaking changes

> **First-run trust default changed from "auto-trust everything" to "deny by default".**
> If you upgrade an existing installation that was implicitly relying on the
> v0.1.x behaviour where every `type: ai-agent-skill` package was auto-registered
> in `AGENTS.md`, you will now see a one-time prompt asking how to seed the
> trust map (`[n] None / [d] Direct deps only / [a] All`). Choose `[a]` to
> preserve the previous behaviour. Non-interactive runs (CI) default to `[n]`
> with a `composer skills:trust …` recovery line per affected package.
>
> See [#42](https://github.com/netresearch/composer-agent-skill-plugin/issues/42)
> and the security review on PR [#43](https://github.com/netresearch/composer-agent-skill-plugin/pull/43)
> for the rationale.

### Added
- **PHP 8.5 and Symfony 8.0 support** in the test matrix and Composer constraints (`symfony/yaml`, `symfony/console`).
- **Symfony 7.4 LTS** added to the test matrix (replacing 7.2, which no longer receives security updates).
- **Composer host matrix** — every PHP × Symfony combination is now tested against both Composer 2.2 LTS and 2.9 (the only supported Composer 2.x release lines).
- **Lowest declared dependency** validation — one extra row resolves with `--prefer-lowest --prefer-stable` against Composer 2.2 LTS, ensuring documented minimums actually install and pass tests.
- **Universal skill discovery**: any Composer package can now ship skills via `extra.ai-agent-skill`, regardless of its declared `type`. Closes [#42](https://github.com/netresearch/composer-agent-skill-plugin/issues/42).
- **Trust prompt**: first-time discovery from a new package prompts the user (`y`/`n`/`a`/`d`) before registering its skills. Decisions persist in root `composer.json` under `extra.ai-agent-skill.allow-skills` with glob support, mirroring Composer's `config.allow-plugins`.
- **First-run policy prompt** for legacy `type: ai-agent-skill` packages: `[n] None / [d] Direct deps only / [a] All`, default `n` (strict). Non-interactive mode defaults to `n` with a per-package `composer skills:trust ...` recovery hint, so CI never silently auto-trusts dependencies. Replaces the earlier prototype's "auto-seed everything" behavior flagged HIGH by the security review.
- **Root package is auto-trusted** — projects that ship their own `SKILL.md` no longer get prompted to authorize themselves.
- `composer list-skills` now shows trust state (`[allowed]` / `[pending]` / `[denied]`) per skill and a footer count of pending packages. The command is purely informational and never prompts.
- `composer skills:trust <package>` command — allow (`composer skills:trust vendor/foo`), deny (`--deny`), or revoke (`--revoke`) a trust decision without hand-editing `composer.json`. Used as the recovery path for accidental denies and as the canonical fix for non-interactive failures.
- `composer skills:list-trust` command — read-only inventory of every persisted decision in `extra.ai-agent-skill.allow-skills`, with `[allowed]` / `[denied]` and `(exact)` / `(glob)` markers per entry. Companion to `skills:trust`; never prompts, never mutates.
- Trust prompt mirrors Composer's plugin prompt shape (`y`/`n`/`a`/`d`/`?`). The `?` answer shows per-option help and re-prompts. The prompt also includes an inline `composer skills:trust <package>` recovery hint so users have an on-screen breadcrumb if they pick `n` by accident.
- `composer read-skill` now shows trust state in the header and warns when reading content from a pending or denied skill (which is not registered in `AGENTS.md`).
- `SkillTrustManager`, `PackageProvider`, `InstalledVersionsProvider`, `PackageInfo`, and `TrustDecision` abstractions for testability.

### Fixed
- **Composer 2.2 LTS compatibility** — `CommandContextTrait` now falls back to the legacy `getComposer(false)` API on Composer 2.2; `tryComposer()` was only introduced in Composer 2.3. Detected by the new `--prefer-lowest` matrix row.

### Changed
- `composer-plugin-api` constraint changed from `^2.1` to `2.2.*|^2.9` — matches the only Composer release lines we test and the only ones that receive upstream support. Composer 2.0/2.1 are out of support; 2.3–2.8 are no longer maintained either.
- `composer/composer` (require-dev) constraint changed from `^2.1` to `2.2.*|^2.9` for the same reason.
- `SkillDiscovery` no longer filters by package `type`. Legacy `type: ai-agent-skill` packages with a root `SKILL.md` continue to work unchanged.
- `SkillDiscovery::discoverAllSkills()` is now pure — it enumerates every declared skill with a `trust_state` field but never prompts. Gating happens at the install/update boundary in `SkillPlugin::updateAgentsMd()` only.
- Non-interactive `composer install` now skips untrusted skill packages with a `composer config --json` hint instead of registering them silently.
- PHPStan level bumped from 8 to 10 (max).

## [1.1.5] - 2026-04-20

### Changed
- Dependency updates via Renovate: `step-security/harden-runner` v2.17.0–2.19.0, `actions/cache` digest refresh, `dependabot/fetch-metadata` v3.x, `codecov/codecov-action` v6.

## [1.1.4] - 2026-03-20

### Security
- **GitHub Actions hardening** ([#31](https://github.com/netresearch/composer-agent-skill-plugin/pull/31)): SHA-pin all third-party actions and add Dependabot for the `github-actions` ecosystem so action updates ship as reviewable PRs rather than floating tags.

### Changed
- Dependency updates via Renovate: `step-security/harden-runner` v2.15.0–2.16.0, `shivammathur/setup-php` digest refresh, `codecov/codecov-action` digest refresh.

## [1.1.3] - 2026-02-09

### Added
- **"Agent Skills" branding and cross-platform compatibility** ([#10](https://github.com/netresearch/composer-agent-skill-plugin/pull/10)).
- **Auto-merge workflow** for vetted dependency updates.
- **Renovate** configuration ([#1](https://github.com/netresearch/composer-agent-skill-plugin/pull/1)).

### Security
- **Pinned GitHub Actions to commit SHAs** with explicit per-job permissions ([#2](https://github.com/netresearch/composer-agent-skill-plugin/pull/2)).

### Changed
- **PHPUnit upgraded to v13** ([#21](https://github.com/netresearch/composer-agent-skill-plugin/pull/21)) — breaking-change adaptations in the test suite.
- Multiple Renovate-driven action and digest updates: `step-security/harden-runner` v2.14.0–2.14.2, `actions/checkout` v6.0.2, `actions/cache` digest refreshes, `dependabot/fetch-metadata` v2.5.0.

## [1.1.2] - 2025-11-26

### Fixed
- **Skills Section Clarity**: Clarified that project skills supplement Claude Code's built-in Skill tool capabilities
  - Changed ambiguous "Only use skills listed" to "For project-specific tasks, only use skills listed"
  - Added note explaining native capabilities remain available alongside project skills
  - Prevents AI agents from ignoring their built-in skill system

## [1.1.1] - 2025-11-25

### Added
- **Working Directory Reminder**: `read-skill` command now displays actionable footer with copy-paste ready `cd` command to help AI agents execute scripts from correct directory

### Changed
- **AGENTS.md Instructions**: Updated base directory instruction from descriptive to imperative language for clearer guidance

## [1.1.0] - 2025-11-25

### Added
- **Symfony 5.4 LTS Support**: Extended compatibility to support Symfony 5.4+ (previously 6.0+)
  - Now supports: Symfony ^5.4|^6.0|^7.0
  - Enables usage in projects still on Symfony 5.4 LTS
- **GitHub Actions CI**: Comprehensive continuous integration workflow
  - Test matrix across PHP 8.2, 8.3, 8.4
  - Test matrix across Symfony 5.4, 6.4, 7.1
  - Lowest dependencies testing (PHP 8.2 + Symfony 5.4)
  - Code quality checks (PHPStan level 8, PHP-CS-Fixer)
  - Code coverage reporting with Codecov integration
  - Automated testing on every push and pull request

### Changed
- **Library Best Practices**: Removed `composer.lock` from repository
  - Libraries should not commit lock files
  - Added `composer.lock` to `.gitignore`
  - Ensures proper dependency resolution for consumers

### Fixed
- **Installation Documentation**: Updated README with accurate Composer 2.2+ plugin authorization requirements
  - Added interactive installation prompt example
  - Added non-interactive/CI installation instructions
  - Documented `allow-plugins` configuration requirement

## [1.0.0] - 2025-11-24

### Added
- **Automatic Skill Discovery**: Discovers all installed packages with type `ai-agent-skill`
- **AGENTS.md Generation**: Generates openskills-compatible XML skill registry
- **CLI Commands**:
  - `composer list-skills` - List all available AI agent skills with package info
  - `composer read-skill <name>` - Display full SKILL.md content for a specific skill
- **SKILL.md Parsing**: Validates and extracts YAML frontmatter following Claude Code specification
- **Multiple Skill Support**: Packages can provide single or multiple skills via configuration
- **Convention Over Configuration**: Zero-config setup with `SKILL.md` in package root
- **Comprehensive Validation**:
  - Name format validation (kebab-case, max 64 chars)
  - Description length validation (max 1024 chars)
  - YAML syntax validation
  - Required field validation (name, description)
- **Edge Case Handling**:
  - Duplicate skill names (last wins with warning)
  - Invalid frontmatter (skip with warning)
  - Missing SKILL.md files (skip with warning)
  - Malformed YAML (skip with detailed error)
- **Configuration Options**:
  - `extra.ai-agent-skill` for custom skill paths
  - Support for single skill (string) or multiple skills (array)
- **Base Directory Support**: Outputs directory containing SKILL.md for resource path resolution
- **Progressive Disclosure**: Lightweight XML index, full details on demand
- **Atomic File Updates**: Safe AGENTS.md updates with temp file + rename
- **Content Preservation**: Updates only `<skills_system>` block, preserves other AGENTS.md content

### Security
- **Absolute Path Rejection**: Blocks absolute paths in `extra.ai-agent-skill` configuration
- **Path Traversal Protection**: Uses `realpath()` to resolve canonical paths
- **XML Escaping**: Properly escapes skill metadata in generated XML

[Unreleased]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.1.5...v2.0.0
[1.1.5]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.1.4...v1.1.5
[1.1.4]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.1.3...v1.1.4
[1.1.3]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/netresearch/composer-agent-skill-plugin/releases/tag/v1.0.0
