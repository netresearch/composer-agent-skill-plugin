# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/netresearch/composer-agent-skill-plugin/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/netresearch/composer-agent-skill-plugin/releases/tag/v1.0.0
