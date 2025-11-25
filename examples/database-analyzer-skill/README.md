# Database Analyzer Skill - Example Package

This is a reference implementation of an AI agent skill package for the Composer AI Agent Skill Plugin.

## Package Structure

```
database-analyzer-skill/
├── composer.json         # Package metadata with type: ai-agent-skill
├── SKILL.md             # Skill definition following Claude Code schema
├── README.md            # This file
├── src/                 # Optional: PHP helper classes
├── references/          # Optional: Reference files (SQL patterns, examples)
├── scripts/             # Optional: Utility scripts
└── assets/              # Optional: Additional resources
```

## Key Components

### composer.json

The most important part is setting the package type:

```json
{
  "name": "example/database-analyzer-skill",
  "type": "ai-agent-skill",    ← This makes it discoverable
  "description": "AI agent skill for database schema analysis"
}
```

### SKILL.md

Must follow the [Claude Code SKILL.md specification](https://code.claude.com/docs/en/skills#write-skill-md):

**Required Frontmatter:**
```yaml
---
name: database-analyzer        # lowercase, numbers, hyphens (max 64 chars)
description: What it does and when to use it (max 1024 chars)
---
```

**Content Structure:**
- Instructions for using the skill
- Examples with real-world scenarios
- Requirements and prerequisites
- Best practices and common patterns

## How It Works

1. **Installation**: User installs this package via Composer
2. **Discovery**: Plugin detects `type: ai-agent-skill` during install/update
3. **Registration**: Plugin parses SKILL.md and registers in AGENTS.md
4. **Usage**: AI agents discover skill via AGENTS.md XML index
5. **Invocation**: Agents execute `composer read-skill database-analyzer`
6. **Execution**: Full SKILL.md content loaded with base directory path

## Publishing Your Skill

### 1. Create Repository

```bash
mkdir my-skill
cd my-skill
git init
```

### 2. Add Files

```bash
# Copy this example as a template
cp -r examples/database-analyzer-skill/* .

# Customize for your skill
vim composer.json  # Update name, description, authors
vim SKILL.md       # Write your skill instructions
```

### 3. Test Locally

```bash
# Create a test project
mkdir ../test-project
cd ../test-project
composer init

# Add your skill as a local path repository
composer config repositories.my-skill path ../my-skill
composer require vendor/my-skill

# Verify registration
composer list-skills
composer read-skill my-skill
```

### 4. Publish to Packagist

```bash
# Tag a release
git tag 1.0.0
git push origin main --tags

# Submit to https://packagist.org
```

## Configuration Options

### Default (Convention)

No configuration needed if SKILL.md is in package root:

```
my-skill/
├── composer.json
├── SKILL.md          ← Auto-discovered
└── src/
```

### Custom Path

For skill in non-root location:

```json
{
  "type": "ai-agent-skill",
  "extra": {
    "ai-agent-skill": "docs/my-skill.md"
  }
}
```

### Multiple Skills

For packages with multiple skills:

```json
{
  "type": "ai-agent-skill",
  "extra": {
    "ai-agent-skill": [
      "skills/analyzer.md",
      "skills/optimizer.md",
      "skills/validator.md"
    ]
  }
}
```

## SKILL.md Requirements

### Name Field
- Format: `^[a-z0-9-]{1,64}$`
- Only lowercase letters, numbers, and hyphens
- Maximum 64 characters
- Examples: `database-analyzer`, `symfony-security`, `api-validator`

### Description Field
- Maximum 1024 characters
- Should explain WHAT the skill does AND WHEN to use it
- Good: "Analyze database schemas and suggest optimizations. Use when working with table structure or query performance."
- Bad: "Database helper" (too vague)

### Optional: allowed-tools

For Claude Code integration:

```yaml
---
name: my-skill
description: What it does
allowed-tools: [Read, Grep, Glob, Bash]  # Restrict tools
---
```

## Bundled Resources

Skills can include additional files:

```
my-skill/
├── SKILL.md
├── references/
│   ├── sql-patterns.sql
│   └── examples.md
├── scripts/
│   ├── analyze.php
│   └── validate.sh
└── assets/
    └── checklist.md
```

**Reference in SKILL.md:**
```markdown
## Resources

See bundled files:
- `references/sql-patterns.sql` - Common SQL patterns
- `scripts/analyze.php` - Analysis automation script

Use the base directory from `composer read-skill` output to locate files.
```

**Agent Usage:**
```bash
$ composer read-skill my-skill
Base Directory: vendor/vendor/my-skill
# Agent can now access: vendor/vendor/my-skill/references/sql-patterns.sql
```

## Validation

Test your SKILL.md:

```bash
# Install the plugin in a test project
composer require netresearch/composer-agent-skill-plugin

# Install your skill
composer require vendor/my-skill

# Check if it appears
composer list-skills

# Read full content
composer read-skill my-skill
```

**Common Issues:**

1. **Skill not found**: Check `type: ai-agent-skill` in composer.json
2. **Invalid frontmatter**: Validate YAML syntax and required fields
3. **Wrong name format**: Use only lowercase, numbers, and hyphens
4. **Description too long**: Keep under 1024 characters

## Tips for Great Skills

✅ **Do:**
- Provide clear, step-by-step instructions
- Include real-world examples with code
- Document prerequisites and requirements
- Explain reasoning behind recommendations
- Keep language clear and concise
- Test thoroughly before publishing

❌ **Don't:**
- Use vague descriptions
- Include outdated information
- Assume knowledge without documenting
- Make skills too broad or too narrow
- Forget to version your releases

## Support

For questions about creating skills:
- [Plugin Documentation](../../README.md)
- [Claude Code SKILL.md Spec](https://code.claude.com/docs/en/skills)
- [openskills Project](https://github.com/numman-ali/openskills)

## License

MIT License - feel free to use this as a template for your own skills.
