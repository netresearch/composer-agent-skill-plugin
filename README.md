# Composer AI Agent Skill Plugin

A Composer plugin that enables universal AI agent skill distribution and management for PHP projects. Automatically discovers, registers, and manages AI agent skills from Composer packages, providing a standardized way for the PHP ecosystem to share agent capabilities.

[![CI](https://github.com/netresearch/composer-agent-skill-plugin/actions/workflows/ci.yml/badge.svg)](https://github.com/netresearch/composer-agent-skill-plugin/actions/workflows/ci.yml)
[![Tests](https://img.shields.io/badge/tests-31%20passing-success)](tests/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-success)](phpstan.neon)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue)](composer.json)
[![Symfony](https://img.shields.io/badge/symfony-5.4%20%7C%206.x%20%7C%207.x-blue)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## 🔌 Compatibility

This is an **Agent Skill** following the [open standard](https://agentskills.io) originally developed by Anthropic and released for cross-platform use.

**Supported Platforms:**
- ✅ Claude Code (Anthropic)
- ✅ Cursor
- ✅ GitHub Copilot
- ✅ Other skills-compatible AI agents

> Skills are portable packages of procedural knowledge that work across any AI agent supporting the Agent Skills specification.


## Features

- **Automatic Discovery**: Finds all packages with type `ai-agent-skill`
- **AGENTS.md Generation**: Creates XML skill index compatible with [openskills](https://github.com/numman-ali/openskills)
- **CLI Commands**: `composer list-skills` and `composer read-skill` for skill inspection
- **Convention Over Configuration**: Works out of the box with zero configuration
- **Progressive Disclosure**: Lightweight index, full details on demand
- **Security First**: Rejects absolute paths, validates all skill metadata
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

## Quick Start

### 1. Install Skill Packages

Install any package with type `ai-agent-skill`:

```bash
composer require vendor/database-analyzer-skill
```

### 2. Skills Auto-Register

The plugin automatically:
- Discovers skill packages during `composer install` or `composer update`
- Generates/updates `AGENTS.md` in your project root
- Registers skills in XML format for AI agent consumption

### 3. Use Skills

AI agents automatically discover skills via `AGENTS.md`:

```bash
# List all available skills
composer list-skills

# Read a specific skill
composer read-skill database-analyzer
```

## Usage

### List Available Skills

```bash
$ composer list-skills

Available AI Agent Skills:

  database-analyzer        vendor/db-skill               1.2.0
  oro-bundle-helper        vendor/oro-skill              1.0.0
  symfony-security         vendor/symfony-security       2.1.0

3 skills available. Use 'composer read-skill <name>' for details.
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

### Basic Skill Package

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
