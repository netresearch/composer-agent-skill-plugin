# Implementation Workflow: Composer AI Agent Skill Plugin

**Generated From:** PRD.md v1.0.0
**Date:** 2025-11-24
**Status:** Ready for Implementation

---

## Overview

This workflow document provides a structured, step-by-step implementation plan for the Composer AI Agent Skill Plugin based on the PRD specifications.

**Implementation Strategy:** Systematic development with incremental validation

**Estimated Duration:** 2-3 weeks (varies by team size and experience)

---

## Table of Contents

1. [Phase 1: Project Foundation](#phase-1-project-foundation)
2. [Phase 2: Core Components](#phase-2-core-components)
3. [Phase 3: Commands & Integration](#phase-3-commands--integration)
4. [Phase 4: Testing & Quality](#phase-4-testing--quality)
5. [Phase 5: Documentation & Release](#phase-5-documentation--release)
6. [Dependencies & Prerequisites](#dependencies--prerequisites)
7. [Quality Gates](#quality-gates)

---

## Phase 1: Project Foundation

**Goal:** Establish project structure, dependencies, and development environment

### Task 1.1: Initialize Project Structure

**Priority:** Critical
**Dependencies:** None

```bash
# Create project directory structure
mkdir -p src/{Commands,Exceptions}
mkdir -p tests/{Unit,Integration,Fixtures}
mkdir -p docs
```

**File Structure:**
```
composer-agent-skill-plugin/
├── src/
│   ├── SkillPlugin.php
│   ├── SkillDiscovery.php
│   ├── AgentsMdGenerator.php
│   ├── Commands/
│   │   ├── ListSkillsCommand.php
│   │   └── ReadSkillCommand.php
│   └── Exceptions/
│       └── InvalidSkillException.php
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Fixtures/
│       └── test-skills/
├── docs/
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── .php-cs-fixer.php
├── README.md
├── LICENSE
└── CHANGELOG.md
```

**Validation:**
- [ ] Directory structure created
- [ ] All directories accessible

---

### Task 1.2: Setup composer.json

**Priority:** Critical
**Dependencies:** Task 1.1

**Create composer.json:**
```json
{
  "name": "psiedler/composer-agent-skill-plugin",
  "description": "Composer plugin for managing AI agent skills",
  "type": "composer-plugin",
  "license": "MIT",
  "authors": [
    {
      "name": "Paul Siedler",
      "email": "paul.siedler@netresearch.de"
    }
  ],
  "require": {
    "php": "^8.1",
    "composer-plugin-api": "^2.1",
    "symfony/yaml": "^6.0|^7.0",
    "symfony/console": "^6.0|^7.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0",
    "phpstan/phpstan": "^1.10",
    "friendsofphp/php-cs-fixer": "^3.0"
  },
  "autoload": {
    "psr-4": {
      "Psiedler\\ComposerAgentSkillPlugin\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Psiedler\\ComposerAgentSkillPlugin\\Tests\\": "tests/"
    }
  },
  "extra": {
    "class": "Psiedler\\ComposerAgentSkillPlugin\\SkillPlugin"
  }
}
```

**Actions:**
```bash
composer install
```

**Validation:**
- [ ] composer.json valid
- [ ] Dependencies installed
- [ ] Autoloading configured

---

### Task 1.3: Setup Development Tools

**Priority:** High
**Dependencies:** Task 1.2

**Configure PHPUnit (phpunit.xml):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

**Configure PHPStan (phpstan.neon):**
```neon
parameters:
    level: 8
    paths:
        - src
    excludePaths:
        - tests
```

**Configure PHP-CS-Fixer (.php-cs-fixer.php):**
```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'strict_param' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
```

**Validation:**
```bash
./vendor/bin/phpunit --version
./vendor/bin/phpstan --version
./vendor/bin/php-cs-fixer --version
```

- [ ] PHPUnit configured
- [ ] PHPStan configured
- [ ] PHP-CS-Fixer configured

---

## Phase 2: Core Components

**Goal:** Implement core plugin functionality

### Task 2.1: Implement SkillPlugin (Main Plugin Class)

**Priority:** Critical
**Dependencies:** Task 1.3

**File:** `src/SkillPlugin.php`

**Key Requirements:**
- Implement `Composer\Plugin\PluginInterface`
- Implement `Composer\Plugin\Capable` for commands
- Implement `EventSubscriberInterface` for event hooks
- Subscribe to `POST_INSTALL_CMD` and `POST_UPDATE_CMD`

**Implementation Checklist:**
- [ ] Class structure created
- [ ] Interfaces implemented
- [ ] Event subscription configured
- [ ] IOInterface dependency injection
- [ ] Composer instance access

**Code Skeleton:**
```php
<?php

declare(strict_types=1);

namespace Psiedler\ComposerAgentSkillPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Script\ScriptEvents;

final class SkillPlugin implements PluginInterface, Capable, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Cleanup if needed
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Cleanup if needed
    }

    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => CommandCapability::class,
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'updateAgentsMd',
            ScriptEvents::POST_UPDATE_CMD => 'updateAgentsMd',
        ];
    }

    public function updateAgentsMd(): void
    {
        // Implementation in Task 2.4
    }
}
```

**Validation:**
- [ ] Class compiles without errors
- [ ] PHPStan level 8 passes
- [ ] PSR-12 compliant

---

### Task 2.2: Implement SkillDiscovery

**Priority:** Critical
**Dependencies:** Task 2.1

**File:** `src/SkillDiscovery.php`

**Key Requirements:**
- Use `Composer\InstalledVersions::getInstalledPackagesByType('ai-agent-skill')`
- Parse SKILL.md frontmatter (YAML)
- Validate name and description fields
- Handle all edge cases with warnings
- Support single/multi-skill packages

**Implementation Checklist:**
- [ ] Constructor with IOInterface
- [ ] `discoverAllSkills()` method
- [ ] `discoverSkillsFromPackage()` method (private)
- [ ] `resolveSkillPaths()` method (private)
- [ ] `parseSkillFile()` method (private)
- [ ] `validateFrontmatter()` method (private)
- [ ] Warning accumulation and output
- [ ] Duplicate skill name handling (last wins)
- [ ] Security check for absolute paths

**Method Signatures:**
```php
public function discoverAllSkills(): array;
private function discoverSkillsFromPackage(string $packageName): array;
private function resolveSkillPaths(string $installPath, $skillConfig, string $packageName): array;
private function parseSkillFile(string $filePath): array;
private function validateFrontmatter(array $frontmatter): void;
private function addWarning(string $package, string $message): void;
private function outputWarnings(): void;
```

**Validation:**
- [ ] Discovers packages correctly
- [ ] Parses YAML frontmatter
- [ ] Validates required fields
- [ ] Handles missing files gracefully
- [ ] Handles malformed YAML gracefully
- [ ] Detects duplicate names
- [ ] Rejects absolute paths
- [ ] Unit tests pass

---

### Task 2.3: Implement AgentsMdGenerator

**Priority:** Critical
**Dependencies:** Task 2.2

**File:** `src/AgentsMdGenerator.php`

**Key Requirements:**
- Generate openskills-compatible XML structure
- Replace `<skills_system>` block only
- Preserve other markdown content
- Alphabetically sort skills by name
- Match exact openskills format

**Implementation Checklist:**
- [ ] `generateSkillsXml()` method
- [ ] `updateAgentsMd()` method
- [ ] XML structure matches openskills exactly
- [ ] Regex pattern for block replacement
- [ ] File creation if AGENTS.md doesn't exist
- [ ] Atomic file writing (write to temp, then rename)

**Method Signatures:**
```php
public function generateSkillsXml(array $skills): string;
public function updateAgentsMd(string $agentsMdPath, array $skills): void;
```

**XML Template:**
```php
private function generateSkillsXml(array $skills): string
{
    // Sort skills alphabetically
    usort($skills, fn($a, $b) => strcmp($a['name'], $b['name']));

    $xml = "<skills_system priority=\"1\">\n\n";
    $xml .= "## Available Skills\n\n";
    $xml .= "<!-- SKILLS_TABLE_START -->\n";
    $xml .= "<usage>\n";
    // ... (full usage text from PRD)
    $xml .= "</usage>\n\n";
    $xml .= "<available_skills>\n\n";

    foreach ($skills as $skill) {
        $xml .= "<skill>\n";
        $xml .= "<name>{$skill['name']}</name>\n";
        $xml .= "<description>{$skill['description']}</description>\n";
        $xml .= "<location>{$skill['location']}</location>\n";
        $xml .= "</skill>\n\n";
    }

    $xml .= "</available_skills>\n";
    $xml .= "<!-- SKILLS_TABLE_END -->\n\n";
    $xml .= "</skills_system>";

    return $xml;
}
```

**Validation:**
- [ ] XML generation correct
- [ ] Block replacement works
- [ ] Other content preserved
- [ ] File creation works
- [ ] Atomic writes implemented
- [ ] Unit tests pass

---

### Task 2.4: Wire Components Together

**Priority:** High
**Dependencies:** Tasks 2.1, 2.2, 2.3

**Update SkillPlugin::updateAgentsMd():**

```php
public function updateAgentsMd(): void
{
    $this->io->write('<info>Updating AI Agent Skills...</info>');

    // Discover skills
    $discovery = new SkillDiscovery($this->io);
    $skills = $discovery->discoverAllSkills();

    if (empty($skills)) {
        return; // SkillDiscovery already outputs appropriate messages
    }

    // Generate and update AGENTS.md
    $generator = new AgentsMdGenerator();
    $projectRoot = getcwd();
    $agentsMdPath = $projectRoot . '/AGENTS.md';

    try {
        $generator->updateAgentsMd($agentsMdPath, $skills);
        $this->io->write(
            sprintf(
                '<info>AI Agent Skills updated: %d skill%s registered in AGENTS.md</info>',
                count($skills),
                count($skills) === 1 ? '' : 's'
            )
        );
    } catch (\Exception $e) {
        $this->io->writeError(
            sprintf('<error>Failed to update AGENTS.md: %s</error>', $e->getMessage())
        );
    }
}
```

**Validation:**
- [ ] Components work together
- [ ] Event triggers plugin
- [ ] Skills discovered
- [ ] AGENTS.md updated
- [ ] Messages output correctly

---

## Phase 3: Commands & Integration

**Goal:** Implement CLI commands for skill inspection

### Task 3.1: Implement ListSkillsCommand

**Priority:** High
**Dependencies:** Task 2.2

**File:** `src/Commands/ListSkillsCommand.php`

**Key Requirements:**
- Extend `Symfony\Component\Console\Command\Command`
- Display name, package, version in columns
- Sort alphabetically by skill name
- Handle empty case gracefully

**Implementation Checklist:**
- [ ] Command configuration
- [ ] Execute method
- [ ] SkillDiscovery integration
- [ ] Columnar output formatting
- [ ] Empty state handling
- [ ] Warning display

**Code Structure:**
```php
<?php

declare(strict_types=1);

namespace Psiedler\ComposerAgentSkillPlugin\Commands;

use Psiedler\ComposerAgentSkillPlugin\SkillDiscovery;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ListSkillsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('list-skills')
            ->setDescription('List all available AI agent skills');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Implementation

        return self::SUCCESS;
    }
}
```

**Validation:**
- [ ] Command registered
- [ ] Output format matches PRD
- [ ] Sorting works
- [ ] Empty case handled
- [ ] Warnings displayed

---

### Task 3.2: Implement ReadSkillCommand

**Priority:** High
**Dependencies:** Task 2.2

**File:** `src/Commands/ReadSkillCommand.php`

**Key Requirements:**
- Accept skill name as argument
- Display full SKILL.md content
- Match openskills output format
- Handle skill not found

**Implementation Checklist:**
- [ ] Command configuration with argument
- [ ] Execute method
- [ ] SkillDiscovery integration
- [ ] Skill lookup by name
- [ ] File content output
- [ ] Error handling with suggestions

**Output Format:**
```
Reading: {skill-name}
Package: {package} v{version}
Location: {path}

{full SKILL.md content}

Skill read: {skill-name}
```

**Validation:**
- [ ] Command registered
- [ ] Argument parsing works
- [ ] Skill found and displayed
- [ ] Error message with suggestions
- [ ] Format matches openskills

---

### Task 3.3: Register Commands (CommandCapability)

**Priority:** High
**Dependencies:** Tasks 3.1, 3.2

**Create:** `src/CommandCapability.php`

```php
<?php

declare(strict_types=1);

namespace Psiedler\ComposerAgentSkillPlugin;

use Composer\Plugin\Capability\CommandProvider;
use Psiedler\ComposerAgentSkillPlugin\Commands\ListSkillsCommand;
use Psiedler\ComposerAgentSkillPlugin\Commands\ReadSkillCommand;

final class CommandCapability implements CommandProvider
{
    public function getCommands(): array
    {
        return [
            new ListSkillsCommand(),
            new ReadSkillCommand(),
        ];
    }
}
```

**Validation:**
```bash
composer list-skills
composer read-skill test-skill
```

- [ ] Commands accessible
- [ ] list-skills works
- [ ] read-skill works

---

## Phase 4: Testing & Quality

**Goal:** Comprehensive test coverage and quality assurance

### Task 4.1: Create Test Fixtures

**Priority:** High
**Dependencies:** None

**Create test skill packages in `tests/Fixtures/`:**

```
tests/Fixtures/
├── valid-single-skill/
│   ├── composer.json (type: ai-agent-skill)
│   └── SKILL.md (valid frontmatter)
├── valid-multi-skill/
│   ├── composer.json (extra.ai-agent-skill array)
│   └── skills/
│       ├── skill-one.md
│       └── skill-two.md
├── invalid-frontmatter/
│   ├── composer.json
│   └── SKILL.md (missing description)
├── malformed-yaml/
│   ├── composer.json
│   └── SKILL.md (invalid YAML)
├── missing-skill-file/
│   └── composer.json (no SKILL.md)
└── duplicate-names/
    ├── package-a/
    │   └── SKILL.md (name: test-skill)
    └── package-b/
        └── SKILL.md (name: test-skill)
```

**Validation:**
- [ ] All fixtures created
- [ ] Valid packages pass validation
- [ ] Invalid packages trigger expected errors

---

### Task 4.2: Unit Tests - SkillDiscovery

**Priority:** Critical
**Dependencies:** Task 4.1

**File:** `tests/Unit/SkillDiscoveryTest.php`

**Test Cases:**
- [ ] `testDiscoverSingleSkillPackage()`
- [ ] `testDiscoverMultiSkillPackage()`
- [ ] `testSkipInvalidFrontmatter()`
- [ ] `testSkipMalformedYaml()`
- [ ] `testSkipMissingSkillFile()`
- [ ] `testDuplicateSkillNamesLastWins()`
- [ ] `testRejectAbsolutePaths()`
- [ ] `testValidateNameFormat()`
- [ ] `testValidateDescriptionLength()`
- [ ] `testWarningAccumulation()`

**Coverage Target:** >90%

---

### Task 4.3: Unit Tests - AgentsMdGenerator

**Priority:** Critical
**Dependencies:** Task 4.1

**File:** `tests/Unit/AgentsMdGeneratorTest.php`

**Test Cases:**
- [ ] `testGenerateSkillsXml()`
- [ ] `testSkillsAlphabeticallySorted()`
- [ ] `testXmlStructureMatchesOpenskills()`
- [ ] `testUpdateAgentsMdCreatesFile()`
- [ ] `testUpdateAgentsMdReplacesBlock()`
- [ ] `testUpdateAgentsMdPreservesContent()`
- [ ] `testMultipleSkillsFormatting()`

**Coverage Target:** >90%

---

### Task 4.4: Integration Tests

**Priority:** High
**Dependencies:** Tasks 4.2, 4.3

**File:** `tests/Integration/PluginIntegrationTest.php`

**Test Scenarios:**
- [ ] Full plugin lifecycle (install → discover → generate)
- [ ] Command execution with real fixtures
- [ ] AGENTS.md generation end-to-end
- [ ] Warning output integration
- [ ] Multiple package types

**Coverage Target:** >70%

---

### Task 4.5: Quality Checks

**Priority:** High
**Dependencies:** All previous tasks

**Run Quality Tools:**

```bash
# Code style
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Static analysis
./vendor/bin/phpstan analyse

# Unit tests with coverage
./vendor/bin/phpunit --coverage-text

# Full test suite
./vendor/bin/phpunit
```

**Quality Gates:**
- [ ] PSR-12 compliant (PHP-CS-Fixer)
- [ ] PHPStan level 8 passes
- [ ] >80% test coverage
- [ ] All tests pass
- [ ] No type errors

---

## Phase 5: Documentation & Release

**Goal:** Complete documentation and prepare for release

### Task 5.1: Write README.md

**Priority:** High
**Dependencies:** All implementation tasks

**Sections:**
- Installation instructions
- Quick start guide
- Usage examples (list-skills, read-skill)
- Creating skill packages
- Configuration options
- Troubleshooting

**Validation:**
- [ ] README complete
- [ ] Examples accurate
- [ ] Links working

---

### Task 5.2: Write CHANGELOG.md

**Priority:** Medium
**Dependencies:** Task 5.1

**Format:**
```markdown
# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2025-MM-DD

### Added
- Initial release
- Automatic skill discovery from composer packages
- AGENTS.md generation with openskills-compatible format
- `composer list-skills` command
- `composer read-skill` command
- Support for single and multi-skill packages
- Graceful error handling with warnings

### Security
- Absolute path rejection for security
```

**Validation:**
- [ ] CHANGELOG created
- [ ] Format follows Keep a Changelog

---

### Task 5.3: Create Example Skill Package

**Priority:** Medium
**Dependencies:** Task 5.1

**Create reference implementation:**
- Example skill package repository
- Complete SKILL.md example
- composer.json configuration
- README for skill authors

**Validation:**
- [ ] Example package works with plugin
- [ ] Documentation clear

---

### Task 5.4: Package for Release

**Priority:** Critical
**Dependencies:** All previous tasks

**Pre-release Checklist:**
- [ ] All tests passing
- [ ] Quality gates passed
- [ ] README complete
- [ ] CHANGELOG updated
- [ ] LICENSE file present
- [ ] composer.json metadata complete
- [ ] Git tags created

**Release Steps:**
```bash
# Tag release
git tag -a v1.0.0 -m "Initial release"
git push origin v1.0.0

# Submit to Packagist
# Visit packagist.org and submit repository
```

**Validation:**
- [ ] Tagged in git
- [ ] Submitted to Packagist
- [ ] Package installable

---

## Dependencies & Prerequisites

### Development Environment

**Required:**
- PHP 8.1+
- Composer 2.1+
- Git

**Recommended:**
- PHPStorm or VS Code
- Xdebug for debugging

### External Dependencies

**Runtime:**
- composer-plugin-api: ^2.1
- symfony/yaml: ^6.0|^7.0
- symfony/console: ^6.0|^7.0

**Development:**
- phpunit/phpunit: ^10.0
- phpstan/phpstan: ^1.10
- friendsofphp/php-cs-fixer: ^3.0

---

## Quality Gates

### Before Each Commit

```bash
# Run all quality checks
composer check

# Or individually:
./vendor/bin/php-cs-fixer fix
./vendor/bin/phpstan analyse
./vendor/bin/phpunit
```

### Before Phase Completion

- [ ] All phase tasks completed
- [ ] Tests passing
- [ ] Code reviewed
- [ ] Documentation updated

### Before Release

- [ ] All phases complete
- [ ] >80% test coverage
- [ ] PHPStan level 8 clean
- [ ] PSR-12 compliant
- [ ] README complete
- [ ] CHANGELOG updated
- [ ] Example package working

---

## Risk Mitigation

### Technical Risks

**Risk:** Composer API changes
**Mitigation:** Pin composer-plugin-api version, monitor Composer releases

**Risk:** YAML parsing edge cases
**Mitigation:** Comprehensive test fixtures, graceful error handling

**Risk:** File system race conditions
**Mitigation:** Atomic file writes, proper locking

### Project Risks

**Risk:** Scope creep
**Mitigation:** Strict adherence to PRD, defer enhancements

**Risk:** Testing complexity
**Mitigation:** Test fixtures early, integration tests for coverage

---

## Success Criteria

### Phase Completion

Each phase is complete when:
- All tasks finished
- Tests passing
- Quality gates passed
- Documentation updated

### Project Completion

Project is complete when:
- All 5 phases done
- Plugin installable via Composer
- Commands functional
- Test coverage >80%
- Documentation complete
- Example package works

---

## Next Steps After Release

1. **Community Feedback:** Monitor issues and feedback
2. **Bug Fixes:** Address reported issues promptly
3. **Skill Packages:** Create initial skill package examples
4. **Documentation:** Expand based on user questions
5. **Marketing:** Blog posts, social media, PHP communities

---

**End of Implementation Workflow**
