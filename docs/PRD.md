# Product Requirements Document: Composer AI Agent Skill Plugin

**Version:** 1.0.0
**Status:** Draft
**Last Updated:** 2025-11-24
**Author:** Paul Siedler

---

## Executive Summary

A Composer plugin that enables universal AI agent skill distribution and management for PHP projects. Inspired by [openskills](https://github.com/numman-ali/openskills), this plugin automatically discovers, registers, and manages AI agent skills from Composer packages, providing a standardized way for the PHP ecosystem to share agent capabilities.

**Goal:** Enable as many PHP developers as possible to use AI agents with the same standardized skill set through familiar Composer package management.

---

## Table of Contents

1. [Problem Statement](#problem-statement)
2. [Solution Overview](#solution-overview)
3. [Technical Architecture](#technical-architecture)
4. [Package Patterns](#package-patterns)
5. [SKILL.md Schema](#skillmd-schema)
6. [AGENTS.md Format](#agentsmd-format)
7. [Commands](#commands)
8. [Package Discovery](#package-discovery)
9. [Edge Case Handling](#edge-case-handling)
10. [Agent Workflow](#agent-workflow)
11. [Implementation Requirements](#implementation-requirements)
12. [Success Metrics](#success-metrics)
13. [References](#references)

---

## Problem Statement

AI agent capabilities in PHP projects are currently:
- **Fragmented:** No standardized distribution mechanism
- **Difficult to share:** Manual setup required per project
- **Hard to discover:** No central registry or automatic detection
- **Inconsistent:** Each project implements skills differently

**Target Users:**
- PHP developers using AI agents (Claude Code, etc.)
- Skill package authors wanting to distribute reusable agent capabilities
- Teams standardizing agent workflows across projects

---

## Solution Overview

A Composer plugin that:

1. **Automatically discovers** packages with type `"ai-agent-skill"`
2. **Parses SKILL.md files** following the [official Claude Code schema](https://code.claude.com/docs/en/skills#write-skill-md)
3. **Generates AGENTS.md** with XML skill index compatible with [openskills format](https://github.com/numman-ali/openskills/blob/main/src/utils/agents-md.ts#L23-L117)
4. **Provides CLI commands** for skill inspection (`list-skills`, `read-skill`)
5. **Manages skill lifecycle** through Composer install/update hooks

### Key Benefits

- **Zero configuration:** Works out of the box with convention over configuration
- **Progressive disclosure:** Lightweight index in AGENTS.md, full details on demand
- **Ecosystem integration:** Uses standard Composer packaging and distribution
- **Compatible:** Follows openskills and Claude Code specifications

---

## Technical Architecture

### Plugin Type
Composer plugin implementing:
- `Composer\Plugin\PluginInterface`
- `Composer\Plugin\Capable` for command registration

**Reference:** [Composer Plugins Documentation](https://getcomposer.org/doc/articles/plugins.md)

### Event Hooks

```php
public function getSubscribedEvents(): array
{
    return [
        ScriptEvents::POST_INSTALL_CMD => 'updateAgentsMd',
        ScriptEvents::POST_UPDATE_CMD => 'updateAgentsMd',
    ];
}
```

### File Management

**AGENTS.md Location:** Project root (alongside composer.json)

**Update Strategy:** Full replacement of `<skills_system>` XML block only, preserving all other markdown content in the file.

**Pattern:**
```php
// Find and replace <skills_system priority="1">...</skills_system> block
// Leave all other content untouched
```

---

## Package Patterns

Skill packages declare type `"ai-agent-skill"` in composer.json.

### Pattern 1: Single Skill (Convention)

**No configuration needed** - plugin assumes `SKILL.md` in package root.

```
vendor/database-analyzer/
├── composer.json
├── SKILL.md          ← Auto-discovered
├── src/
└── README.md
```

**composer.json:**
```json
{
  "name": "vendor/database-analyzer",
  "type": "ai-agent-skill",
  "description": "Database analysis tools for AI agents"
}
```

### Pattern 2: Multi-Skill Package

**Multiple skills** declared in `extra.ai-agent-skill` array.

```
vendor/database-tools/
├── composer.json
├── skills/
│   ├── analyzer.md
│   ├── optimizer.md
│   └── validator.md
└── src/
```

**composer.json:**
```json
{
  "name": "vendor/database-tools",
  "type": "ai-agent-skill",
  "description": "Suite of database tools for AI agents",
  "extra": {
    "ai-agent-skill": [
      "skills/analyzer.md",
      "skills/optimizer.md",
      "skills/validator.md"
    ]
  }
}
```

### Pattern 3: Single Skill with Custom Path

**Non-standard location** specified as string.

```json
{
  "name": "vendor/custom-skill",
  "type": "ai-agent-skill",
  "extra": {
    "ai-agent-skill": "docs/agent-skill.md"
  }
}
```

**Security:** Absolute paths are rejected for security reasons. Only relative paths from package root are allowed.

---

## SKILL.md Schema

Follows the [official Claude Code SKILL.md specification](https://code.claude.com/docs/en/skills#write-skill-md).

### Frontmatter Format

```yaml
---
name: skill-name
description: Brief description of what this skill does and when to use it
allowed-tools: [Read, Grep, Glob]  # Optional, Claude Code only
---
```

### Required Fields

| Field | Format | Constraints |
|-------|--------|-------------|
| `name` | String | Lowercase letters, numbers, hyphens only (`^[a-z0-9-]{1,64}$`); max 64 characters |
| `description` | String | Explains functionality AND activation triggers; max 1024 characters |

### Optional Fields

| Field | Format | Purpose |
|-------|--------|---------|
| `allowed-tools` | Array or comma-separated | Tool restrictions (Claude Code feature) |

### Markdown Content Structure

```markdown
---
name: my-skill
description: What it does and when to use it
---

# My Skill

## Instructions

Step-by-step guidance for using this skill...

## Examples

Example 1: Analyze users table
Example 2: Optimize query performance

## Requirements (optional)

- PHP 8.1+
- Database access
```

### Validation Rules

1. **YAML Syntax:** Valid YAML with spaces for indentation (not tabs)
2. **Delimiters:** `---` on lines 1 and 3
3. **Required fields:** Both `name` and `description` must be present and non-empty
4. **Name format:** Lowercase alphanumeric and hyphens only; no spaces or special characters
5. **Character limits:** Name ≤64 chars, description ≤1024 chars

---

## AGENTS.md Format

Follows the [openskills XML structure](https://github.com/numman-ali/openskills/blob/main/src/utils/agents-md.ts#L23-L117).

### Complete Structure

```xml
<skills_system priority="1">

## Available Skills

<!-- SKILLS_TABLE_START -->
<usage>
When users ask you to perform tasks, check if any of the available skills below can help complete the task more effectively. Skills provide specialized capabilities and domain knowledge.

How to use skills:
- Invoke: Bash("composer read-skill <skill-name>")
- The skill content will load with detailed instructions on how to complete the task
- Base directory provided in output for resolving bundled resources (references/, scripts/, assets/)

Usage notes:
- For project-specific tasks, only use skills listed in <available_skills> below
- Note: Native capabilities (e.g., via the Skill tool) remain available alongside project skills
- Do not invoke a skill that is already loaded in your context
- Each skill invocation is stateless
</usage>

<available_skills>

<skill>
<name>database-analyzer</name>
<description>Analyze and optimize database schemas and relationships</description>
<location>vendor/vendor/db-skill</location>
</skill>

<skill>
<name>oro-bundle-helper</name>
<description>OroCommerce bundle development and structure guidance</description>
<location>vendor/vendor/oro-skill</location>
</skill>

</available_skills>
<!-- SKILLS_TABLE_END -->

</skills_system>
```

### Key Elements

- **Root element:** `<skills_system priority="1">`
- **Skill elements:** No attributes (name/description/location are child elements)
- **Comment markers:** `<!-- SKILLS_TABLE_START -->` and `<!-- SKILLS_TABLE_END -->`
- **Skill ordering:** Alphabetical by name
- **Spacing:** Double newlines between skills

### XML Child Elements

| Element | Source | Description |
|---------|--------|-------------|
| `<name>` | SKILL.md frontmatter | Skill name (kebab-case) |
| `<description>` | SKILL.md frontmatter | Brief description |
| `<location>` | Package install path | Vendor directory path (e.g., `vendor/vendor-name/package-name`) |

**Note:** No additional metadata (package name, version, allowed-tools) is included in the XML. This information is available via `composer read-skill`.

---

## Commands

### `composer list-skills`

Lists all available AI agent skills with package and version information.

**Output Format:**
```bash
$ composer list-skills

Available AI Agent Skills:

  database-analyzer        vendor/db-skill               1.2.0
  oro-bundle-helper        vendor/oro-skill              1.0.0
  symfony-security         vendor/symfony-security       2.1.0

3 skills available. Use 'composer read-skill <name>' for details.
```

**Features:**
- Simple columnar layout (no table borders)
- Sorted alphabetically by skill name
- Shows: name, package, version
- Count summary with usage hint

**No skills found:**
```bash
 [WARNING] No AI agent skills found in installed packages.

 ! [NOTE] Install packages with type "ai-agent-skill" to use skills.
```

### `composer read-skill <name>`

Displays full SKILL.md content for a specific skill. Format follows [openskills read command](https://github.com/numman-ali/openskills/blob/main/src/commands/read.ts).

**Output Format:**
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

[... full SKILL.md content ...]

Skill read: database-analyzer
```

**Error handling:**
```bash
$ composer read-skill nonexistent-skill

Error: Skill 'nonexistent-skill' not found

Available skills:
  - database-analyzer (vendor/db-skill)
  - oro-bundle-helper (vendor/oro-skill)
```

---

## Package Discovery

Uses Composer's runtime API for type-based package discovery.

**Reference:** [Composer Runtime API - Package Types](https://getcomposer.org/doc/07-runtime.md#knowing-which-packages-of-a-given-type-are-installed)

### Discovery Implementation

```php
use Composer\InstalledVersions;

// Find all packages with type "ai-agent-skill"
$packageNames = InstalledVersions::getInstalledPackagesByType('ai-agent-skill');

foreach ($packageNames as $packageName) {
    $installPath = InstalledVersions::getInstallPath($packageName);
    $version = InstalledVersions::getPrettyVersion($packageName);

    // Discover skills from package
    $skills = $this->discoverSkillsFromPackage($packageName, $installPath, $version);
}
```

### Discovery Algorithm

1. **Get all packages** with type `"ai-agent-skill"`
2. **For each package:**
   - Read `composer.json` to check for `extra.ai-agent-skill` configuration
   - Resolve skill file paths:
     - **No config:** Use `SKILL.md` in package root (convention)
     - **String config:** Use specified path
     - **Array config:** Use all specified paths (multi-skill)
3. **For each SKILL.md path:**
   - Check if file exists
   - Parse YAML frontmatter
   - Validate required fields
   - Extract skill metadata
4. **Return skill data array** with package info

### Skill Data Structure

```php
[
    'name' => 'database-analyzer',           // From SKILL.md frontmatter
    'description' => 'Analyze and optimize...', // From SKILL.md frontmatter
    'package' => 'vendor/db-skill',          // Package name
    'version' => '1.2.0',                    // Package version
    'location' => '/path/vendor/vendor/db-skill', // Install path
    'path' => '/path/vendor/vendor/db-skill/SKILL.md', // Full file path
    'allowed-tools' => ['Read', 'Grep'],     // Optional from frontmatter
]
```

---

## Edge Case Handling

All edge cases are handled gracefully with warnings (non-fatal). The plugin continues operation and reports issues to the user.

### 1. Duplicate Skill Names

**Scenario:** Two packages provide skills with the same name.

**Behavior:** Last one wins + warning

**Example:**
```
Package A: vendor/tools-a/SKILL.md → name: "database-analyzer"
Package B: vendor/tools-b/SKILL.md → name: "database-analyzer"
```

**Output:**
```
AI Agent Skill Plugin Warnings:
  [vendor/tools-b] Duplicate skill name 'database-analyzer' (already defined by vendor/tools-a).
                   Using skill from vendor/tools-b (last one wins).
```

### 2. Invalid Frontmatter

**Scenario:** SKILL.md missing required fields or violating validation rules.

**Behavior:** Skip skill + warning

**Examples:**
- Missing `name` or `description`
- Invalid name format (not kebab-case)
- Description exceeds 1024 characters
- Name exceeds 64 characters

**Output:**
```
AI Agent Skill Plugin Warnings:
  [vendor/broken-skill] Invalid frontmatter in 'SKILL.md': Missing required field: 'description'
```

### 3. Missing SKILL.md Files

**Scenario:** Package declares type `"ai-agent-skill"` but SKILL.md not found.

**Behavior:** Skip package + warning with instructions

**Output:**
```
AI Agent Skill Plugin Warnings:
  [vendor/missing-skill] SKILL.md not found at 'SKILL.md'.
                         Expected SKILL.md in package root (convention).
```

**With custom configuration:**
```
AI Agent Skill Plugin Warnings:
  [vendor/custom-path] SKILL.md not found at 'docs/skill.md'.
                       Check 'extra.ai-agent-skill' configuration in composer.json.
```

### 4. Malformed YAML

**Scenario:** SKILL.md frontmatter contains invalid YAML syntax.

**Behavior:** Skip skill + warning with parse error

**Output:**
```
AI Agent Skill Plugin Warnings:
  [vendor/broken-yaml] Malformed YAML in 'SKILL.md':
                       A colon cannot be used in an unquoted mapping value at line 3
```

### 5. Security: Absolute Paths

**Scenario:** Package configuration uses absolute paths.

**Behavior:** Reject path + warning

```json
{
  "extra": {
    "ai-agent-skill": "/absolute/path/skill.md"  // Not allowed
  }
}
```

**Output:**
```
AI Agent Skill Plugin Warnings:
  [vendor/unsafe-config] Absolute paths not allowed in 'extra.ai-agent-skill'.
                         Use relative paths from package root.
```

### Warning Output Format

All warnings are grouped and displayed after discovery:

```bash
$ composer install

[... normal composer output ...]

AI Agent Skill Plugin Warnings:
  [vendor/package-a] Warning message here
  [vendor/package-b] Another warning message

AI Agent Skills updated: 2 skills registered in AGENTS.md
```

---

## Agent Workflow

Progressive disclosure pattern for efficient agent operation.

### Discovery Phase

1. **Agent starts in project** → Reads `AGENTS.md` in project root
2. **Parses XML index** → Gets lightweight overview of available skills
   - Skill names
   - Brief descriptions
   - Package locations
3. **Identifies relevant skills** → Based on user request and skill descriptions

### Invocation Phase

4. **User requests specific capability** → Agent recognizes matching skill
5. **Agent executes** → `composer read-skill <skill-name>`
6. **Gets full SKILL.md content** → Complete instructions, examples, requirements
7. **Agent applies skill** → Follows detailed instructions from SKILL.md

### Benefits

- **Fast initial load:** AGENTS.md XML is small and quick to parse
- **On-demand details:** Full SKILL.md loaded only when needed
- **Reduced context:** Agents don't load all skill details upfront
- **Stateless:** Each skill invocation is independent

### Example Flow

```
User: "Analyze the users table for optimization opportunities"

Agent reasoning:
1. Reads AGENTS.md → Sees "database-analyzer" skill available
2. Matches description: "Analyze and optimize database schemas"
3. Executes: composer read-skill database-analyzer
4. Receives full SKILL.md with instructions
5. Applies skill: Analyzes table using provided guidance
```

---

## Implementation Requirements

### Composer Requirements

- **Composer version:** 2.1+ (for `InstalledVersions::getInstalledPackagesByType()`)
- **PHP version:** 8.1+ (recommended for modern PHP features)
- **Composer runtime API:** `^2.1`

### Dependencies

```json
{
  "require": {
    "php": "^8.1",
    "composer-plugin-api": "^2.1",
    "symfony/yaml": "^6.0|^7.0",
    "symfony/console": "^6.0|^7.0"
  }
}
```

### Plugin Registration

**composer.json:**
```json
{
  "name": "vendor/composer-agent-skill-plugin",
  "type": "composer-plugin",
  "require": {
    "composer-plugin-api": "^2.1"
  },
  "extra": {
    "class": "YourVendor\\ComposerSkillPlugin\\SkillPlugin"
  },
  "autoload": {
    "psr-4": {
      "YourVendor\\ComposerSkillPlugin\\": "src/"
    }
  }
}
```

### File Structure

```
src/
├── SkillPlugin.php              (Main plugin class)
├── SkillDiscovery.php           (Package & skill discovery)
├── AgentsMdGenerator.php        (XML generation)
├── Commands/
│   ├── ListSkillsCommand.php   (list-skills implementation)
│   └── ReadSkillCommand.php    (read-skill implementation)
└── Exceptions/
    └── InvalidSkillException.php
```

### Testing Strategy

1. **Unit Tests:** Individual components (parser, validator, generator)
2. **Integration Tests:** Full plugin lifecycle with test packages
3. **Edge Case Tests:** All error scenarios and warnings
4. **Compatibility Tests:** Different Composer versions and PHP versions

### Quality Standards

- **PSR-12:** Code style
- **PSR-4:** Autoloading
- **PHPStan Level 8:** Static analysis
- **100% type coverage:** All parameters and returns typed
- **Comprehensive tests:** >80% code coverage

---

## Success Metrics

### Adoption Metrics

- Number of skill packages published
- Number of projects using the plugin
- GitHub stars/forks
- Packagist downloads

### Quality Metrics

- Plugin reliability (error rates)
- Skill discovery success rate
- Warning frequency by type
- User-reported issues

### Ecosystem Growth

- Number of skill authors
- Skill category diversity
- Framework-specific skill coverage (Symfony, Laravel, etc.)
- Average skills per project

---

## References

### Official Documentation

- [Composer Plugin Documentation](https://getcomposer.org/doc/articles/plugins.md)
- [Composer Runtime API - Package Discovery](https://getcomposer.org/doc/07-runtime.md#knowing-which-packages-of-a-given-type-are-installed)
- [Claude Code - SKILL.md Schema](https://code.claude.com/docs/en/skills#write-skill-md)

### Inspiration & Prior Art

- [openskills - Universal AI Agent Skills](https://github.com/numman-ali/openskills)
  - [Technical Deep Dive](https://github.com/numman-ali/openskills?tab=readme-ov-file#how-it-works-technical-deep-dive)
  - [AGENTS.md XML Generation](https://github.com/numman-ali/openskills/blob/main/src/utils/agents-md.ts#L23-L117)
  - [Skill Reading Implementation](https://github.com/numman-ali/openskills/blob/main/src/utils/skills.ts#L10-L61)
  - [Read Command Format](https://github.com/numman-ali/openskills/blob/main/src/commands/read.ts)

### Related Projects

- [Composer Installers](https://github.com/composer/installers) - Custom install paths
- [Symfony Flex](https://github.com/symfony/flex) - Recipe-based package configuration

---

## Appendix A: Example Skill Package

### composer.json

```json
{
  "name": "example/database-analyzer-skill",
  "description": "AI agent skill for database schema analysis and optimization",
  "type": "ai-agent-skill",
  "license": "MIT",
  "authors": [
    {
      "name": "Your Name",
      "email": "your.email@example.com"
    }
  ],
  "require": {
    "php": "^8.1"
  },
  "autoload": {
    "psr-4": {
      "Example\\DatabaseAnalyzer\\": "src/"
    }
  }
}
```

### SKILL.md

```markdown
---
name: database-analyzer
description: Analyze and optimize database schemas, identify performance issues, and suggest improvements. Use when working with database structure, indexes, or query performance.
---

# Database Analyzer Skill

This skill helps you analyze database schemas, identify optimization opportunities, and understand table relationships.

## Instructions

1. **Identify the target:** Determine which table or schema to analyze
2. **Gather context:** Understand the current usage patterns and performance concerns
3. **Analyze structure:** Examine table definitions, indexes, and relationships
4. **Identify issues:** Look for missing indexes, improper data types, or inefficient structures
5. **Suggest improvements:** Provide specific, actionable recommendations

## Examples

### Example 1: Basic Table Analysis

**User request:** "Analyze the users table for optimization opportunities"

**Approach:**
- Check table structure and data types
- Verify indexes on frequently queried columns
- Look for redundant or missing indexes
- Suggest appropriate data types for columns

### Example 2: Performance Investigation

**User request:** "Why are queries on the orders table slow?"

**Approach:**
- Identify frequently executed queries
- Check for missing indexes on WHERE/JOIN columns
- Analyze table size and growth patterns
- Suggest partitioning if appropriate

## Requirements

- Access to database schema information
- Understanding of SQL and database design principles
- Ability to read EXPLAIN query plans (if available)

## Best Practices

- Always explain the reasoning behind suggestions
- Consider both read and write performance impacts
- Account for data volume and growth patterns
- Suggest incremental improvements when possible
```

---

## Appendix B: AGENTS.md Example

Complete example of generated AGENTS.md in a project:

```markdown
# AI Agent Guidelines

This project uses Symfony 6 and follows PSR-12 coding standards.

## Development Guidelines

- Always use dependency injection
- Write tests for new features
- Run `composer check` before committing

<skills_system priority="1">

## Available Skills

<!-- SKILLS_TABLE_START -->
<usage>
When users ask you to perform tasks, check if any of the available skills below can help complete the task more effectively. Skills provide specialized capabilities and domain knowledge.

How to use skills:
- Invoke: Bash("composer read-skill <skill-name>")
- The skill content will load with detailed instructions on how to complete the task
- Base directory provided in output for resolving bundled resources (references/, scripts/, assets/)

Usage notes:
- For project-specific tasks, only use skills listed in <available_skills> below
- Note: Native capabilities (e.g., via the Skill tool) remain available alongside project skills
- Do not invoke a skill that is already loaded in your context
- Each skill invocation is stateless
</usage>

<available_skills>

<skill>
<name>database-analyzer</name>
<description>Analyze and optimize database schemas and relationships</description>
<location>vendor/example/database-analyzer-skill</location>
</skill>

<skill>
<name>symfony-security</name>
<description>Symfony security best practices and vulnerability scanning</description>
<location>vendor/example/symfony-security-skill</location>
</skill>

</available_skills>
<!-- SKILLS_TABLE_END -->

</skills_system>

## Additional Resources

See docs/architecture.md for system design details.
```

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | 2025-11-24 | Paul Siedler | Initial PRD from brainstorming session |

---

**End of Product Requirements Document**
