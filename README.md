# Composer AI Agent Skill Plugin

A Composer plugin for **AI agent skills** in PHP projects. Skills are **skill dependencies** of your repo: you declare them through **one or more** of the mechanisms below (they are equivalent in the sense that everything flows into the same discovery, trust, and **`AGENTS.md`** pipeline—you may **combine** them). Versioning and publishing differ (semver packages vs pinned git lock), not whether the path is “official.”

[![CI](https://github.com/netresearch/composer-agent-skill-plugin/actions/workflows/ci.yml/badge.svg)](https://github.com/netresearch/composer-agent-skill-plugin/actions/workflows/ci.yml)
[![Tests](https://img.shields.io/badge/tests-164%20passing-success)](tests/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%2010%20(max)-success)](phpstan.neon)
[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](composer.json)
[![Symfony](https://img.shields.io/badge/symfony-5.4%20%7C%206.4%20%7C%207.4%20%7C%208.0-blue)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Features

- **Three ways to bring skills in** — see [How skills enter your project](#how-skills-enter-your-project): (1) `composer require` a **`type: ai-agent-skill`** package, (2) `composer require` **any package** that lists skills under **`extra.ai-agent-skill`**, (3) declare **GitHub or in-repo paths** under **`extra.ai-agent-skills`** with **`composer.skills.lock`** and **`composer skills …`** ([Direct sources](#direct-sources-github-and-project-paths)).
- **Automatic discovery**: Finds skills from installed packages (legacy type and universal `extra` form).
- **AGENTS.md generation**: Creates XML skill index compatible with [openskills](https://github.com/numman-ali/openskills)
- **CLI commands**: `composer list-skills`, `composer read-skill`, trust helpers, **`composer skills:*`** for direct sources, and **`composer outdated`** appends direct-skill rows (text) or points to **`composer skills:outdated`**
- **Convention Over Configuration**: Works out of the box with zero configuration
- **Progressive Disclosure**: Lightweight index, full details on demand
- **Security First**: Rejects unsafe paths, validates skill metadata (including descriptions bound for AGENTS.md)
- **Multiple Skills Per Package**: Support for both single and multi-skill packages

## Installation

```bash
composer require netresearch/composer-agent-skill-plugin
```

During installation, Composer will prompt you to authorize the plugin:

```
netresearch/composer-agent-skill-plugin contains a Composer plugin which is currently not in your allow-plugins config.
Do you trust "netresearch/composer-agent-skill-plugin" to execute code and wish to enable it now? [y,n,d,?]
```

Choose `y` to allow the plugin to activate.

### Non-Interactive Installation

For CI/CD or non-interactive environments, pre-authorize the plugin in your `composer.json`:

```json
{
    "config": {
        "allow-plugins": {
            "netresearch/composer-agent-skill-plugin": true
        }
    }
}
```

## How skills enter your project

1. **`composer require` a dedicated skill package** — `type: ai-agent-skill` (classic).
2. **`composer require` any package that ships skills** — `extra.ai-agent-skill` on libraries, bundles, etc. ([universal discovery](docs/adr/001-universal-discovery.md)).
3. **Direct sources** — root [`extra.ai-agent-skills`](docs/IMPLEMENTATION-DIRECT-SKILLS.md) + **`composer.skills.lock`** + **`composer skills …`**. Same trust / `AGENTS.md` pipeline as (1) and (2); you can use **several** of these together.

## Quick Start

### 1. Install Skill Packages

Install any package with type `ai-agent-skill`:

```bash
composer require vendor/database-analyzer-skill
```

### 2. Skills Auto-Register (after trust)

The plugin automatically:
- Discovers skill packages during `composer install` or `composer update`
- **Asks once** before registering skills from a package it hasn't seen before — see [Trust Model](#trust-model)
- Generates/updates `AGENTS.md` in your project root with the trusted skills
- Registers them in XML format for AI agent consumption

### 3. Use Skills

AI agents automatically discover skills via `AGENTS.md`:

```bash
# List all available skills
composer list-skills

# Read a specific skill
composer read-skill database-analyzer
```

> **Design rationale**: see [docs/adr/](docs/adr/) for short Architecture Decision Records covering universal discovery, the trust prompt design, atomic-write persistence, glob semantics, direct skills (009–012), and more.

## Direct sources: GitHub and project paths

Path (3) above: pin skills from **GitHub** or from a **directory inside the project** using `extra.ai-agent-skills` and a generated lockfile **`composer.skills.lock`**. Full technical notes: [`docs/IMPLEMENTATION-DIRECT-SKILLS.md`](docs/IMPLEMENTATION-DIRECT-SKILLS.md).

### Requirements

- **`git`** on `PATH` when using GitHub sources (clone/fetch).
- Supported source strings match [`SourceResolver`](src/Source/SourceResolver.php): GitHub HTTPS/SSH, `owner/repo` shorthand (with optional `:ref` suffix), GitHub **tree** URLs, and existing local directories. **Generic non-GitHub git HTTPS URLs are not supported yet.**

### Semver on git tags (like `composer require`)

For GitHub shorthand (or `--ref`), you can use **Composer-style semver constraints** against **annotated/lightweight tags** on the remote (`git ls-remote --tags`). Examples:

```bash
# Highest tag satisfying ^1.2 (e.g. 1.2.6), stored as ref "^1.2" in composer.json; lock pins the commit
composer skills:add acme/some-skill-repo:^1.2 --skill=my-skill

# Same, via flag
composer skills:add acme/some-skill-repo --skill=my-skill --ref='^1.2'
```

On **`composer update`**, the plugin’s post-update hook **re-resolves** the constraint against current remote tags and refreshes **`composer.skills.lock`** (same idea as relaxing a lock when you bump constraints—here the constraint lives in `extra.ai-agent-skills.sources[].ref`). **`composer install`** still applies the **pinned commit** from the lock only.

Constraints are detected when the ref contains semver operators (`^`, `~`, `*`, `>=`, ranges with `,` / `||`, etc.). Plain names like `main` or `v1.2.3` are still treated as **literal git refs**.

### Outdated checks (`composer outdated` and `composer skills:outdated`)

- **`composer outdated`** (same as `composer show --latest --outdated`) lists Composer packages as usual. When you use **text** output, this plugin appends a short block for **direct agent skills** whose **`composer.skills.lock`** pin is behind the current **remote git tip** (for semver, branch, or tag sources) or whose **path** skill content hash changed on disk. Resolving uses **`git ls-remote`** (read-only); failures are skipped with a comment on stderr.
- **`composer outdated -f json`** must stay valid JSON for package rows only. If direct skills are stale, the plugin prints a **stderr notice**; run **`composer skills:outdated -f json`** for a machine-readable `{"outdated":[…]}` document.
- **`composer skills:outdated`** — full list; add **`--strict`** for exit code **1** when anything is outdated (handy in CI). **`composer outdated --strict`** still reflects **Composer packages only** (unchanged upstream behaviour).

### Typical workflow

```bash
# Add a source and resolve skills into composer.skills.lock (writes composer.json + lock)
composer skills:add vercel-labs/skills --skill=find-skills --ref=main

# On CI or a fresh clone: install exactly what the lock pins (no implicit network update)
composer install

# After editing sources in composer.json: refresh the lock + installed files
composer skills:update
```

Equivalent forms: `composer skills add …` and `composer skills:add …` (same for `install`, `update`, `remove`, `list`, `outdated`).

### Configuration keys (`extra.ai-agent-skills`)

| Key | Purpose |
| --- | --- |
| `version` | Schema version (currently `1`) |
| `install-dir` | Where skill trees are materialized (default `vendor/agent-skills/installed/`) |
| `cache-dir` | **Git clone** and temporary work directories (default `vendor/agent-skills/cache`) |
| `sources-dir` | Declared directory prefix (included in the content-hash); clone cache uses **`cache-dir`**, not this path |
| `sources` | List of sources (`type`: `path`, `github`, or `git` with `url` / `path` / `ref` / `skills`) |

Relative directory keys must not use `..` or absolute paths. Lockfile `install-path` / `path` / path-type `url` fields are checked the same way so a tampered lock cannot write outside the project.

### Trust for direct skills

Direct skills use the **same** `extra.ai-agent-skill.allow-skills` map as packages. Keys look like:

`direct:<source-name>/<skill-id>`

Use `composer list-skills` to see the exact `package` column value, then:

```bash
composer skills:trust 'direct:vercel-labs/skills/find-skills'
```

See [ADR-012](docs/adr/012-skills-content-hash-and-trust.md): changing allow/deny does **not** change the lock **`content-hash`**; changing **sources** does.

### Disable direct-skill sync

Set environment variable:

`COMPOSER_AGENT_SKILLS=0`

…to skip direct-skill install/update hooks (legacy AGENTS.md behaviour is unchanged).

## Trust Model

Skills are instructions an AI agent will follow in your project. To prevent transitive dependencies from silently injecting skills, the plugin asks before registering skills from a new package — modeled on Composer's own `allow-plugins` flow.

The first time a new package wants to register skills, you'll see:

```
Package "vendor/foo" wants to register AI agent skills.
Skills are instructions an AI agent will follow in your project.
Allow this package to register skills?
  [y] Yes — allow & persist (writes to composer.json)
  [n] No — deny & persist (suppress future prompts)
  [a] Allow for this session only
  [d] Discard — leave undecided, ask again next run
  [?] Show details about this choice
(change later with: composer skills:trust vendor/foo [--deny|--revoke])
(defaults to n) [y,n,a,d,?]:
```

Decisions persist under `extra.ai-agent-skill.allow-skills` in your root `composer.json`:

```json
{
  "extra": {
    "ai-agent-skill": {
      "allow-skills": {
        "vendor/foo": true,
        "vendor/bar": false,
        "trusted-org/*": true
      }
    }
  }
}
```

### Managing trust decisions

Use the dedicated commands (preferred):

```bash
composer skills:trust vendor/foo            # allow & persist
composer skills:trust vendor/foo --deny     # deny & persist
composer skills:trust vendor/foo --revoke   # remove from the map (re-prompts next install)
composer skills:list-trust                  # show every persisted decision
```

Or edit `composer.json` directly. Glob patterns (`vendor/*`) are supported. **Direct skills** use keys `direct:<source>/<skill-name>` in the same map — see [Direct sources](#direct-sources-github-and-project-paths).

> ⚠️ **Glob matching is case-sensitive and `*` matches any characters within a pattern segment.** Composer normalizes published package names to lowercase, so write trust patterns in lowercase. A pattern like `acme/skills-*` also trusts `acme/skills-anything-else` — use globs only for namespaces you fully control. Exact-string keys always override matching globs, even when the glob was added first. Prefer explicit per-package entries when in doubt.

In non-interactive mode (`composer install --no-interaction`, CI), packages without an explicit decision are skipped with a warning — the plugin never auto-trusts on your behalf. The warning suggests `composer skills:trust <package>` so CI failures are one command away from a fix. `composer list-skills` shows the trust state per skill (`[allowed]` / `[pending]` / `[denied]`) without firing prompts.

### First-run policy (covers v0.1.x upgrades)

The first time the plugin runs in a project that already has `type: ai-agent-skill` packages installed — typically a fresh install of those packages or an upgrade from v0.1.x where they were silently auto-trusted — you'll see a one-time prompt asking how to seed the trust map:

```
The AI Agent Skill plugin found 3 existing skill packages that have not been authorized yet.
  - vendor/skill-a   (in your require)
  - vendor/skill-b   (pulled in by another package)
  - vendor/skill-c   (pulled in by another package)
How should they be trusted on this first run?
  [n] None — prompt for each package later (default, strict)
  [d] Direct dependencies only — auto-trust packages your root composer.json explicitly requires
  [a] All — auto-trust every existing skill package (including transitive)
(defaults to n) [n,d,a]:
```

- **`n` (default, recommended)** — nothing is auto-trusted. Each package goes through the per-package prompt during the same install.
- **`d`** — only packages listed directly in your root `composer.json`'s `require`/`require-dev` are auto-trusted. Transitive skill packages still prompt.
- **`a`** — every existing skill package is auto-trusted. This restores the v0.1.x behaviour at the user's explicit consent — fastest, widest trust surface.

The choice is persisted under `extra.ai-agent-skill.allow-skills`, so the prompt only fires once. In non-interactive mode (`composer install --no-interaction`, CI) the policy defaults to `n` and a warning lists every affected package with the `composer skills:trust ...` recovery command. CI installs never silently expand trust on your behalf.

Library-bundled skills (`type: library` + `extra.ai-agent-skill`) are **not** part of this first-run policy — they always go through the per-package prompt because the user chose the library for its primary purpose, not to import skills.

## Usage

### List Available Skills

```bash
$ composer list-skills

Available AI Agent Skills:

  database-analyzer        vendor/db-skill               1.2.0       [allowed]
  oro-bundle-helper        vendor/oro-skill              1.0.0       [allowed]
  symfony-security         vendor/symfony-security       2.1.0       [pending]

3 skills available. Use 'composer read-skill <name>' for details.
1 pending — run `composer install` interactively to be prompted.
```

### Read Skill Details

```bash
$ composer read-skill database-analyzer

Reading: database-analyzer
Package: vendor/db-skill v1.2.0
Base Directory: vendor/vendor/db-skill

---
name: database-analyzer
description: Analyze and optimize database schemas and relationships
---

# Database Analyzer Skill

[Full SKILL.md content with instructions and examples]

Skill read: database-analyzer
```

The **Base Directory** is the directory containing SKILL.md, used as the root for resolving bundled resources like `references/`, `scripts/`, and `assets/`.

## Creating Skill Packages

There are two ways to ship skills, both fully supported:

### Option A: Library bundles a skill (recommended for libraries)

Any package — regardless of `type` — can ship skills by declaring `extra.ai-agent-skill`:

```json
{
  "name": "vendor/my-library",
  "type": "library",
  "extra": {
    "ai-agent-skill": "skills/my-helper.md"
  }
}
```

This mirrors the pattern `phpstan/extension-installer` uses for PHPStan extensions: the library opts in via `extra`, no special package type required. Use this when an existing library wants to ship a companion skill alongside its primary purpose.

### Option B: Dedicated skill package

A package whose only purpose is shipping skills can use `type: ai-agent-skill` and place `SKILL.md` in the package root with no extra config:

**1. Create composer.json:**

```json
{
  "name": "vendor/my-skill",
  "description": "My awesome AI agent skill",
  "type": "ai-agent-skill",
  "license": "MIT",
  "require": {
    "php": "^8.2"
  }
}
```

**2. Create SKILL.md in package root:**

```markdown
---
name: my-skill
description: Brief description of what this skill does and when to use it
---

# My Skill

## Instructions

Step-by-step guidance for using this skill...

## Examples

Example 1: How to use feature X
Example 2: How to handle scenario Y

## Requirements

- PHP 8.2+
- Any other dependencies
```

**3. Publish to Packagist:**

```bash
git tag 1.0.0
git push --tags
```

### Multi-Skill Package

For packages containing multiple skills, configure paths in `extra.ai-agent-skill`:

```json
{
  "name": "vendor/database-tools",
  "type": "ai-agent-skill",
  "require": {
    "netresearch/composer-agent-skill-plugin": "*"
  },
  "extra": {
    "ai-agent-skill": [
      "skills/analyzer/SKILL.md",
      "skills/optimizer/SKILL.md",
      "skills/validator/SKILL.md"
    ]
  }
}
```

### Custom Skill Path

For a single skill in a non-standard location:

```json
{
  "name": "vendor/custom-skill",
  "type": "ai-agent-skill",
  "require": {
    "netresearch/composer-agent-skill-plugin": "*"
  },
  "extra": {
    "ai-agent-skill": "docs/agent-skill.md"
  }
}
```

**Security Note:** Only relative paths from package root are allowed. Absolute paths are rejected.

## SKILL.md Schema

Skills must follow the [Claude Code SKILL.md specification](https://code.claude.com/docs/en/skills#write-skill-md):

### Required Frontmatter

```yaml
---
name: skill-name          # lowercase, numbers, hyphens only (max 64 chars)
description: Clear description of functionality and triggers (max 1024 chars)
---
```

### Optional Frontmatter

```yaml
---
name: my-skill
description: What it does and when to use it
allowed-tools: [Read, Grep, Glob]  # Claude Code only
---
```

### Validation Rules

- **Name format**: `^[a-z0-9-]{1,64}$` (lowercase alphanumeric and hyphens)
- **Name length**: Maximum 64 characters
- **Description length**: Maximum 1024 characters
- **YAML syntax**: Valid YAML with proper delimiters (`---`)

## Configuration Options

### Default (Convention)

No configuration needed. Plugin looks for `SKILL.md` in package root:

```
vendor/my-skill/
├── composer.json
├── SKILL.md          ← Auto-discovered
└── src/
```

### Custom Single Skill

```json
{
  "extra": {
    "ai-agent-skill": "custom/path/skill.md"
  }
}
```

### Multiple Skills

```json
{
  "extra": {
    "ai-agent-skill": [
      "skills/skill-one.md",
      "skills/skill-two.md",
      "docs/skill-three.md"
    ]
  }
}
```

## Troubleshooting

### No Skills Found

```
[WARNING] No AI agent skills found in installed packages.
```

**Solution**: Install packages with `"type": "ai-agent-skill"` in their composer.json.

### Duplicate Skill Names

```
[vendor/tools-b] Duplicate skill name 'database-analyzer' (already defined by vendor/tools-a).
                 Using skill from vendor/tools-b (last one wins).
```

**Behavior**: Last package wins. Consider renaming skills to avoid conflicts.

### Invalid Frontmatter

```
[vendor/broken-skill] Invalid frontmatter in 'SKILL.md': Missing required field: 'description'
```

**Solution**: Ensure SKILL.md has both `name` and `description` fields in valid YAML format.

### Malformed YAML

```
[vendor/broken-yaml] Malformed YAML in 'SKILL.md':
                     A colon cannot be used in an unquoted mapping value at line 3
```

**Solution**: Fix YAML syntax. Use spaces (not tabs), quote values with colons.

### Missing SKILL.md

```
[vendor/missing-skill] SKILL.md not found at 'SKILL.md'.
                       Expected SKILL.md in package root (convention).
```

**Solution**: Create SKILL.md in package root or configure `extra.ai-agent-skill` path.

### Absolute Path Rejected

```
[vendor/unsafe-config] Absolute paths not allowed in 'extra.ai-agent-skill'.
                       Use relative paths from package root.
```

**Solution**: Use relative paths like `"skills/analyzer.md"` instead of `/absolute/path`.

## How It Works

### Discovery Process

1. Plugin hooks into `composer install` and `composer update` events
2. Finds all packages with type `ai-agent-skill`
3. Reads each package's `composer.json` for skill paths
4. Parses SKILL.md files and validates frontmatter
5. Generates XML skill registry in `AGENTS.md`

### AGENTS.md Structure

```xml
<skills_system priority="1">

## Available Skills

<!-- SKILLS_TABLE_START -->
<usage>
When users ask you to perform tasks, check if any of the available skills below can help complete the task more effectively.

How to use skills:
- Invoke: Bash("composer read-skill <skill-name>")
- The skill content will load with detailed instructions
- Base directory provided in output for resolving bundled resources
</usage>

<available_skills>

<skill>
<name>database-analyzer</name>
<description>Analyze and optimize database schemas</description>
<location>vendor/vendor/db-skill</location>
</skill>

</available_skills>
<!-- SKILLS_TABLE_END -->

</skills_system>
```

### Progressive Disclosure

- **AGENTS.md**: Lightweight index with skill names and descriptions
- **read-skill**: Full SKILL.md content loaded on demand
- **Benefits**: Fast discovery, reduced context size, on-demand details

## Requirements

- PHP 8.2 or higher
- Composer 2.1 or higher
- Symfony YAML Component 5.4+, 6.0+, or 7.0+
- Symfony Console Component 5.4+, 6.0+, or 7.0+

## Development

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run with coverage
./vendor/bin/phpunit --coverage-text

# Run specific test
./vendor/bin/phpunit tests/Unit/SkillDiscoveryTest.php
```

### Code Quality

```bash
# PHPStan static analysis (level 8)
./vendor/bin/phpstan analyse

# PHP CS Fixer (PSR-12)
./vendor/bin/php-cs-fixer fix --allow-risky=yes

# Check without fixing
./vendor/bin/php-cs-fixer fix --dry-run --allow-risky=yes
```

### Project Structure

```
src/
├── Commands/
│   ├── ListSkillsCommand.php    # composer list-skills
│   └── ReadSkillCommand.php     # composer read-skill
├── Exceptions/
│   └── InvalidSkillException.php
├── AgentsMdGenerator.php         # AGENTS.md generation
├── CommandCapability.php         # Command registration
├── SkillDiscovery.php            # Package discovery
└── SkillPlugin.php               # Main plugin class

tests/
├── Unit/                         # Unit tests
├── Integration/                  # Integration tests
└── Fixtures/                     # Test fixtures
```

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`./vendor/bin/phpunit`)
5. Run static analysis (`./vendor/bin/phpstan analyse`)
6. Fix code style (`./vendor/bin/php-cs-fixer fix --allow-risky=yes`)
7. Commit your changes (`git commit -m 'Add amazing feature'`)
8. Push to the branch (`git push origin feature/amazing-feature`)
9. Open a Pull Request

### Code Standards

- PSR-12 coding style
- PHPStan level 8
- 100% type coverage
- Comprehensive tests (>80% coverage)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Inspiration

Inspired by [openskills](https://github.com/numman-ali/openskills) - Universal AI Agent Skills for standardized skill distribution across development environments.

## Links

- [Composer Plugin Documentation](https://getcomposer.org/doc/articles/plugins.md)
- [Claude Code - SKILL.md Schema](https://code.claude.com/docs/en/skills#write-skill-md)
- [openskills Project](https://github.com/numman-ali/openskills)

## Support

- **Issues**: [GitHub Issues](https://github.com/netresearch/composer-agent-skill-plugin/issues)
- **Discussions**: [GitHub Discussions](https://github.com/netresearch/composer-agent-skill-plugin/discussions)

---

**Made with ❤️ by Netresearch**
