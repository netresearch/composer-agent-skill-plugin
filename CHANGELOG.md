# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Universal skill discovery**: any Composer package can now ship skills via `extra.ai-agent-skill`, regardless of its declared `type`. Closes [#42](https://github.com/netresearch/composer-agent-skill-plugin/issues/42).
- **Trust prompt**: first-time discovery from a new package prompts the user (`y`/`n`/`a`/`d`) before registering its skills. Decisions persist in root `composer.json` under `extra.ai-agent-skill.allow-skills` with glob support, mirroring Composer's `config.allow-plugins`.
- **First-run policy prompt** for legacy `type: ai-agent-skill` packages: `[n] None / [d] Direct deps only / [a] All`, default `n` (strict). Non-interactive mode defaults to `n` with a per-package `composer skills:trust ...` recovery hint, so CI never silently auto-trusts dependencies. Replaces the earlier prototype's "auto-seed everything" behavior flagged HIGH by the security review.
- **Root package is auto-trusted** — projects that ship their own `SKILL.md` no longer get prompted to authorize themselves.
- `composer list-skills` now shows trust state (`[allowed]` / `[pending]` / `[denied]`) per skill and a footer count of pending packages. The command is purely informational and never prompts.
- `composer skills:trust <package>` command — allow (`composer skills:trust vendor/foo`), deny (`--deny`), or revoke (`--revoke`) a trust decision without hand-editing `composer.json`. Used as the recovery path for accidental denies and as the canonical fix for non-interactive failures.
- `composer read-skill` now shows trust state in the header and warns when reading content from a pending or denied skill (which is not registered in `AGENTS.md`).
- `SkillTrustManager`, `PackageProvider`, `InstalledVersionsProvider`, `PackageInfo`, and `TrustDecision` abstractions for testability.

### Changed
- `SkillDiscovery` no longer filters by package `type`. Legacy `type: ai-agent-skill` packages with a root `SKILL.md` continue to work unchanged.
- `SkillDiscovery::discoverAllSkills()` is now pure — it enumerates every declared skill with a `trust_state` field but never prompts. Gating happens at the install/update boundary in `SkillPlugin::updateAgentsMd()` only.
- Non-interactive `composer install` now skips untrusted skill packages with a `composer config --json` hint instead of registering them silently.
- PHPStan level bumped from 8 to 10 (max).

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

[Unreleased]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.1.2...HEAD
[1.1.2]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/netresearch/composer-agent-skill-plugin/releases/tag/v1.0.0
