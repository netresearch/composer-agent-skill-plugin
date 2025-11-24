# Agent Implementation Prompts

**Purpose:** Structured prompts for AI agents to implement the Composer AI Agent Skill Plugin
**Source Documents:** PRD.md, WORKFLOW.md
**Last Updated:** 2025-11-24

---

## How to Use This Document

Each phase has a specific prompt designed for AI agent execution. Follow these guidelines:

1. **Sequential Execution:** Complete phases in order (1 → 5)
2. **Validation:** Always validate previous phase before starting new phase
3. **Documentation:** Update PROGRESS.md after each task completion
4. **Quality Gates:** Run quality checks before marking phase complete
5. **Context Files:** Reference PRD.md and WORKFLOW.md for specifications

---

## Phase 1: Project Foundation

### Agent Prompt

```
I need you to implement Phase 1 (Project Foundation) of the Composer AI Agent Skill Plugin as specified in WORKFLOW.md.

CONTEXT:
- Read @WORKFLOW.md Phase 1 section completely
- Reference @PRD.md for technical requirements
- This is the initial project setup phase

REQUIREMENTS:

Task 1.1: Initialize Project Structure
- Create all directories as specified in WORKFLOW.md Phase 1, Task 1.1
- Verify directory structure matches the specification exactly

Task 1.2: Setup composer.json
- Create composer.json with exact specifications from WORKFLOW.md Task 1.2
- Use package name: psiedler/composer-agent-skill-plugin
- Include all required and dev dependencies
- Configure PSR-4 autoloading
- Run `composer install` and verify success

Task 1.3: Setup Development Tools
- Create phpunit.xml configuration
- Create phpstan.neon configuration
- Create .php-cs-fixer.php configuration
- Verify all tools are accessible via composer

VALIDATION CHECKLIST:
After completing all tasks, validate:
1. Run: `find . -type d` and confirm structure matches WORKFLOW.md
2. Run: `composer validate` - must pass
3. Run: `composer install` - must complete without errors
4. Run: `./vendor/bin/phpunit --version` - must output version
5. Run: `./vendor/bin/phpstan --version` - must output version
6. Run: `./vendor/bin/php-cs-fixer --version` - must output version

PROGRESS DOCUMENTATION:
Create/update PROGRESS.md with:

## Phase 1: Project Foundation - [COMPLETED/IN_PROGRESS/BLOCKED]

### Completed Tasks:
- [x] Task 1.1: Project structure created
- [x] Task 1.2: composer.json configured
- [x] Task 1.3: Development tools setup

### Validation Results:
- [ ] Directory structure verified
- [ ] composer.json valid
- [ ] Dependencies installed
- [ ] PHPUnit accessible
- [ ] PHPStan accessible
- [ ] PHP-CS-Fixer accessible

### Issues Encountered:
[List any issues and resolutions]

### Next Steps:
Phase 2: Core Components

---

IMPORTANT:
- Do NOT proceed to Phase 2 until ALL validation checks pass
- If any validation fails, fix issues before marking phase complete
- Document any deviations from WORKFLOW.md specifications
```

---

## Phase 2: Core Components

### Agent Prompt

```
I need you to implement Phase 2 (Core Components) of the Composer AI Agent Skill Plugin as specified in WORKFLOW.md.

PREREQUISITE VALIDATION:
Before starting, verify Phase 1 completion:
1. Check PROGRESS.md shows Phase 1 completed
2. Verify composer.json exists and is valid
3. Verify vendor/ directory exists
4. Verify development tools are installed

If any checks fail, STOP and complete Phase 1 first.

CONTEXT:
- Read @WORKFLOW.md Phase 2 section completely
- Reference @PRD.md sections: Technical Architecture, Package Discovery, Edge Case Handling
- This phase implements core plugin functionality

REQUIREMENTS:

Task 2.1: Implement SkillPlugin (Main Plugin Class)
- Create src/SkillPlugin.php
- Implement PluginInterface, Capable, EventSubscriberInterface
- Add composer and io properties
- Implement activate(), deactivate(), uninstall() methods
- Implement getCapabilities() method
- Implement getSubscribedEvents() method (POST_INSTALL_CMD, POST_UPDATE_CMD)
- Create placeholder updateAgentsMd() method (implement in Task 2.4)
- Add strict_types declaration
- Ensure 100% type coverage

Task 2.2: Implement SkillDiscovery
- Create src/SkillDiscovery.php
- Implement constructor with IOInterface parameter
- Implement discoverAllSkills() method - returns array of skill data
- Implement private discoverSkillsFromPackage() method
- Implement private resolveSkillPaths() method - handles convention, string config, array config
- Implement private parseSkillFile() method - parse YAML frontmatter
- Implement private validateFrontmatter() method - validate name (kebab-case, max 64) and description (max 1024)
- Implement private addWarning() and outputWarnings() methods
- Handle duplicate skill names (last wins + warning)
- Reject absolute paths with warning
- Handle missing files, invalid frontmatter, malformed YAML gracefully
- Add strict_types declaration

Task 2.3: Implement AgentsMdGenerator
- Create src/AgentsMdGenerator.php
- Implement generateSkillsXml() method - returns XML string
- Implement updateAgentsMd() method - file operations
- Match EXACT openskills XML format from PRD.md
- Sort skills alphabetically by name
- Use regex to replace <skills_system> block only
- Preserve all other markdown content
- Create file if doesn't exist
- Implement atomic writes (temp file + rename)
- Add strict_types declaration

Task 2.4: Wire Components Together
- Update SkillPlugin::updateAgentsMd() implementation
- Instantiate SkillDiscovery with $this->io
- Call discoverAllSkills()
- Instantiate AgentsMdGenerator
- Get project root with getcwd()
- Call updateAgentsMd() with AGENTS.md path and skills
- Add try-catch with error messages
- Output success message with skill count

VALIDATION CHECKLIST:
After completing all tasks:
1. Run: `./vendor/bin/php-cs-fixer fix` - must pass or fix issues
2. Run: `./vendor/bin/phpstan analyse` - must have zero errors
3. Verify all classes have declare(strict_types=1)
4. Verify all methods have type hints
5. Check SkillPlugin implements required interfaces
6. Check SkillDiscovery uses InstalledVersions API correctly
7. Check AgentsMdGenerator XML matches PRD.md exactly
8. Check error handling is graceful (warnings, not exceptions)

PROGRESS DOCUMENTATION:
Update PROGRESS.md with:

## Phase 2: Core Components - [COMPLETED/IN_PROGRESS/BLOCKED]

### Completed Tasks:
- [x] Task 2.1: SkillPlugin implemented
- [x] Task 2.2: SkillDiscovery implemented
- [x] Task 2.3: AgentsMdGenerator implemented
- [x] Task 2.4: Components wired together

### Validation Results:
- [ ] PHP-CS-Fixer passes
- [ ] PHPStan level 8 passes
- [ ] All type hints present
- [ ] Strict types declared
- [ ] Interfaces implemented correctly
- [ ] InstalledVersions API used correctly
- [ ] XML format matches PRD.md
- [ ] Error handling graceful

### Code Quality Metrics:
- PHPStan errors: [count]
- Type coverage: [percentage]
- Files created: [list]

### Issues Encountered:
[List any issues and resolutions]

### Next Steps:
Phase 3: Commands & Integration

---

IMPORTANT:
- Reference PRD.md for EXACT specifications (XML format, validation rules, error handling)
- Use Composer\InstalledVersions for package discovery (NOT manual vendor scanning)
- Implement ALL edge cases from PRD.md section "Edge Case Handling"
- XML must match openskills format EXACTLY (from PRD.md "AGENTS.md Format")
- Do NOT proceed to Phase 3 until ALL validation checks pass
```

---

## Phase 3: Commands & Integration

### Agent Prompt

```
I need you to implement Phase 3 (Commands & Integration) of the Composer AI Agent Skill Plugin as specified in WORKFLOW.md.

PREREQUISITE VALIDATION:
Before starting, verify Phase 2 completion:
1. Check PROGRESS.md shows Phase 2 completed
2. Verify src/SkillPlugin.php exists and implements required interfaces
3. Verify src/SkillDiscovery.php exists
4. Verify src/AgentsMdGenerator.php exists
5. Run: `./vendor/bin/phpstan analyse` - must pass
6. Run: `./vendor/bin/php-cs-fixer fix --dry-run` - must pass

If any checks fail, STOP and complete Phase 2 first.

CONTEXT:
- Read @WORKFLOW.md Phase 3 section completely
- Reference @PRD.md section "Commands" for exact specifications
- This phase implements CLI commands

REQUIREMENTS:

Task 3.1: Implement ListSkillsCommand
- Create src/Commands/ directory
- Create src/Commands/ListSkillsCommand.php
- Extend Symfony\Component\Console\Command\Command
- Configure command name: 'list-skills'
- Configure command description
- Implement execute() method:
  - Instantiate SkillDiscovery
  - Call discoverAllSkills()
  - Sort skills alphabetically by name
  - Output columnar format (name, package, version) as specified in PRD.md
  - Handle empty case with warning message
  - Display warning count if discovery had warnings
  - Return Command::SUCCESS
- Match EXACT output format from PRD.md
- Add strict_types declaration

Task 3.2: Implement ReadSkillCommand
- Create src/Commands/ReadSkillCommand.php
- Extend Symfony\Component\Console\Command\Command
- Configure command name: 'read-skill'
- Add required argument: 'name' (skill name)
- Configure command description
- Implement execute() method:
  - Get skill name from argument
  - Instantiate SkillDiscovery
  - Call discoverAllSkills()
  - Find skill by name in results
  - If not found: display error + list available skills, return Command::FAILURE
  - If found: display formatted output as specified in PRD.md
  - Output format: "Reading: {name}", "Package: {package} v{version}", "Location: {path}", blank line, full file content, blank line, "Skill read: {name}"
  - Return Command::SUCCESS
- Match EXACT output format from PRD.md (openskills format)
- Add strict_types declaration

Task 3.3: Register Commands
- Create src/CommandCapability.php
- Implement Composer\Plugin\Capability\CommandProvider
- Implement getCommands() method returning array of command instances
- Return [new ListSkillsCommand(), new ReadSkillCommand()]
- Verify SkillPlugin::getCapabilities() returns CommandProvider::class => CommandCapability::class
- Add strict_types declaration

VALIDATION CHECKLIST:
After completing all tasks:
1. Run: `./vendor/bin/php-cs-fixer fix` - must pass
2. Run: `./vendor/bin/phpstan analyse` - must pass with zero errors
3. Verify command registration in SkillPlugin
4. Verify CommandCapability returns both commands
5. Check output formats match PRD.md EXACTLY
6. Verify error handling in ReadSkillCommand

MANUAL TESTING:
Since plugin is not yet installed, we can't test commands directly. Instead:
1. Review code against PRD.md specifications
2. Verify SkillDiscovery integration
3. Verify output format strings match PRD.md
4. Confirm error messages match PRD.md

PROGRESS DOCUMENTATION:
Update PROGRESS.md with:

## Phase 3: Commands & Integration - [COMPLETED/IN_PROGRESS/BLOCKED]

### Completed Tasks:
- [x] Task 3.1: ListSkillsCommand implemented
- [x] Task 3.2: ReadSkillCommand implemented
- [x] Task 3.3: Commands registered

### Validation Results:
- [ ] PHP-CS-Fixer passes
- [ ] PHPStan level 8 passes
- [ ] CommandCapability implemented
- [ ] Commands registered in SkillPlugin
- [ ] Output formats match PRD.md
- [ ] Error handling present

### Code Review:
- Output format verified against PRD.md: [YES/NO]
- SkillDiscovery integration correct: [YES/NO]
- Error messages match specification: [YES/NO]

### Files Created:
- src/Commands/ListSkillsCommand.php
- src/Commands/ReadSkillCommand.php
- src/CommandCapability.php

### Issues Encountered:
[List any issues and resolutions]

### Next Steps:
Phase 4: Testing & Quality

---

IMPORTANT:
- Output formats MUST match PRD.md EXACTLY (see "Commands" section)
- ListSkillsCommand: columnar format, no table borders, sorted alphabetically
- ReadSkillCommand: openskills format with header, content, footer
- Do NOT proceed to Phase 4 until ALL validation checks pass
```

---

## Phase 4: Testing & Quality

### Agent Prompt

```
I need you to implement Phase 4 (Testing & Quality) of the Composer AI Agent Skill Plugin as specified in WORKFLOW.md.

PREREQUISITE VALIDATION:
Before starting, verify Phase 3 completion:
1. Check PROGRESS.md shows Phase 3 completed
2. Verify src/Commands/ListSkillsCommand.php exists
3. Verify src/Commands/ReadSkillCommand.php exists
4. Verify src/CommandCapability.php exists
5. Run: `./vendor/bin/phpstan analyse` - must have zero errors
6. Run: `./vendor/bin/php-cs-fixer fix --dry-run` - must pass

If any checks fail, STOP and complete Phase 3 first.

CONTEXT:
- Read @WORKFLOW.md Phase 4 section completely
- Reference @PRD.md for edge case specifications
- This phase implements comprehensive test coverage

REQUIREMENTS:

Task 4.1: Create Test Fixtures
- Create tests/Fixtures/ directory structure as specified in WORKFLOW.md
- Create valid-single-skill/ with proper composer.json and SKILL.md
- Create valid-multi-skill/ with composer.json (extra.ai-agent-skill array) and skills/
- Create invalid-frontmatter/ with SKILL.md missing description
- Create malformed-yaml/ with invalid YAML in SKILL.md
- Create missing-skill-file/ with only composer.json
- Create duplicate-names/ with two packages having same skill name
- Ensure all fixtures are realistic and follow PRD.md specifications

Task 4.2: Unit Tests - SkillDiscovery
- Create tests/Unit/ directory
- Create tests/Unit/SkillDiscoveryTest.php
- Extend PHPUnit\Framework\TestCase
- Implement test methods:
  - testDiscoverSingleSkillPackage()
  - testDiscoverMultiSkillPackage()
  - testSkipInvalidFrontmatter()
  - testSkipMalformedYaml()
  - testSkipMissingSkillFile()
  - testDuplicateSkillNamesLastWins()
  - testRejectAbsolutePaths()
  - testValidateNameFormat()
  - testValidateDescriptionLength()
  - testWarningAccumulation()
- Use fixtures from Task 4.1
- Mock IOInterface for testing warnings
- Target: >90% code coverage for SkillDiscovery

Task 4.3: Unit Tests - AgentsMdGenerator
- Create tests/Unit/AgentsMdGeneratorTest.php
- Extend PHPUnit\Framework\TestCase
- Implement test methods:
  - testGenerateSkillsXml()
  - testSkillsAlphabeticallySorted()
  - testXmlStructureMatchesOpenskills()
  - testUpdateAgentsMdCreatesFile()
  - testUpdateAgentsMdReplacesBlock()
  - testUpdateAgentsMdPreservesContent()
  - testMultipleSkillsFormatting()
- Verify XML output matches PRD.md EXACTLY
- Test file operations with temporary files
- Target: >90% code coverage for AgentsMdGenerator

Task 4.4: Integration Tests
- Create tests/Integration/ directory
- Create tests/Integration/PluginIntegrationTest.php
- Test full plugin lifecycle
- Test command execution
- Test AGENTS.md generation end-to-end
- Use test fixtures
- Target: >70% integration coverage

Task 4.5: Run Quality Checks
- Run: `./vendor/bin/php-cs-fixer fix`
- Run: `./vendor/bin/phpstan analyse`
- Run: `./vendor/bin/phpunit`
- Run: `./vendor/bin/phpunit --coverage-text`
- Fix any issues found
- Ensure all tests pass
- Verify coverage >80%

VALIDATION CHECKLIST:
After completing all tasks:
1. All test fixtures created and realistic
2. SkillDiscoveryTest has ≥10 test methods
3. AgentsMdGeneratorTest has ≥7 test methods
4. Integration tests present
5. Run: `./vendor/bin/phpunit` - ALL tests must pass
6. Run: `./vendor/bin/phpunit --coverage-text` - coverage must be >80%
7. Run: `./vendor/bin/phpstan analyse` - zero errors
8. Run: `./vendor/bin/php-cs-fixer fix` - passes or auto-fixed
9. No skipped or incomplete tests
10. No warnings in test output

PROGRESS DOCUMENTATION:
Update PROGRESS.md with:

## Phase 4: Testing & Quality - [COMPLETED/IN_PROGRESS/BLOCKED]

### Completed Tasks:
- [x] Task 4.1: Test fixtures created
- [x] Task 4.2: SkillDiscovery unit tests (XX tests)
- [x] Task 4.3: AgentsMdGenerator unit tests (XX tests)
- [x] Task 4.4: Integration tests
- [x] Task 4.5: Quality checks passed

### Validation Results:
- [ ] All fixtures created
- [ ] Unit tests implemented
- [ ] Integration tests implemented
- [ ] All tests passing
- [ ] Coverage >80%
- [ ] PHPStan passes
- [ ] PHP-CS-Fixer passes

### Test Metrics:
- Total tests: [count]
- Assertions: [count]
- Code coverage: [percentage]
- PHPStan errors: [count]
- Style violations: [count]

### Test Failures:
[List any test failures and how they were resolved]

### Coverage Report:
```
[Paste coverage summary from --coverage-text]
```

### Issues Encountered:
[List any issues and resolutions]

### Next Steps:
Phase 5: Documentation & Release

---

IMPORTANT:
- Use realistic test fixtures that match PRD.md specifications
- Test ALL edge cases from PRD.md "Edge Case Handling" section
- Verify XML output matches openskills format EXACTLY
- Ensure >80% code coverage before proceeding
- All tests must pass - no skipped tests
- Do NOT proceed to Phase 5 until ALL validation checks pass
```

---

## Phase 5: Documentation & Release

### Agent Prompt

```
I need you to implement Phase 5 (Documentation & Release) of the Composer AI Agent Skill Plugin as specified in WORKFLOW.md.

PREREQUISITE VALIDATION:
Before starting, verify Phase 4 completion:
1. Check PROGRESS.md shows Phase 4 completed
2. Verify tests/ directory structure exists with Unit/ and Integration/
3. Run: `./vendor/bin/phpunit` - ALL tests must pass
4. Run: `./vendor/bin/phpunit --coverage-text` - coverage must be >80%
5. Run: `./vendor/bin/phpstan analyse` - must have zero errors
6. Run: `./vendor/bin/php-cs-fixer fix --dry-run` - must pass

If any checks fail, STOP and complete Phase 4 first.

CONTEXT:
- Read @WORKFLOW.md Phase 5 section completely
- Reference @PRD.md for feature descriptions
- This phase completes documentation and prepares for release

REQUIREMENTS:

Task 5.1: Write README.md
- Create comprehensive README.md in project root
- Include sections:
  - Project title and description
  - Features list (from PRD.md)
  - Installation instructions (`composer require psiedler/composer-agent-skill-plugin`)
  - Quick start guide
  - Usage examples for both commands
  - Creating skill packages section with example
  - Configuration options (extra.ai-agent-skill)
  - Troubleshooting section
  - Contributing guidelines
  - License information
- Use clear examples
- Include code blocks with syntax highlighting
- Keep it user-friendly and concise

Task 5.2: Write CHANGELOG.md
- Create CHANGELOG.md following Keep a Changelog format
- Add [1.0.0] section with current date
- Include subsections:
  - Added: List all features
  - Security: Note about absolute path rejection
- Keep format consistent for future updates

Task 5.3: Create Example Skill Package
- Create examples/ directory
- Create examples/database-analyzer-skill/ as reference implementation
- Include realistic composer.json with type: ai-agent-skill
- Include complete SKILL.md following PRD.md specifications
- Include README.md explaining the skill package structure
- Make it copy-paste ready for skill authors

Task 5.4: Verify LICENSE File
- Confirm LICENSE file exists (MIT license)
- Ensure copyright year and author correct

Task 5.5: Final Pre-Release Validation
- Verify composer.json metadata complete:
  - name, description, type, license
  - authors with name and email
  - keywords for discoverability
  - homepage/repository URLs if available
- Verify all source files have proper headers
- Verify no TODO comments in production code
- Run full quality check suite

VALIDATION CHECKLIST:
After completing all tasks:
1. README.md is comprehensive and clear
2. README.md examples are accurate
3. CHANGELOG.md follows Keep a Changelog format
4. Example skill package is complete and realistic
5. LICENSE file present with correct info
6. composer.json metadata complete
7. Run: `./vendor/bin/phpunit` - all tests pass
8. Run: `./vendor/bin/phpstan analyse` - zero errors
9. Run: `./vendor/bin/php-cs-fixer fix` - passes
10. No TODO/FIXME comments in src/
11. All documentation links working
12. Project ready for git tag and release

RELEASE READINESS CHECKLIST:
- [ ] All 5 phases completed
- [ ] All tests passing (100%)
- [ ] Code coverage >80%
- [ ] PHPStan level 8 clean
- [ ] PSR-12 compliant
- [ ] README.md complete
- [ ] CHANGELOG.md present
- [ ] Example package working
- [ ] LICENSE file present
- [ ] No TODOs in source code
- [ ] Git repository clean

PROGRESS DOCUMENTATION:
Update PROGRESS.md with:

## Phase 5: Documentation & Release - [COMPLETED/IN_PROGRESS/BLOCKED]

### Completed Tasks:
- [x] Task 5.1: README.md written
- [x] Task 5.2: CHANGELOG.md created
- [x] Task 5.3: Example skill package created
- [x] Task 5.4: LICENSE verified
- [x] Task 5.5: Final validation passed

### Validation Results:
- [ ] README.md comprehensive
- [ ] CHANGELOG.md formatted correctly
- [ ] Example package complete
- [ ] LICENSE present
- [ ] composer.json metadata complete
- [ ] All quality checks pass
- [ ] No TODOs in source
- [ ] Documentation accurate

### Documentation Quality:
- README.md sections: [count]
- Code examples: [count]
- Links verified: [YES/NO]
- Spelling/grammar checked: [YES/NO]

### Release Preparation:
- Version number: 1.0.0
- Release date: [date]
- Git tag ready: [YES/NO]
- Packagist submission ready: [YES/NO]

### Final Metrics:
- Total files: [count]
- Total tests: [count]
- Code coverage: [percentage]
- PHPStan level: 8 (zero errors)
- Lines of code: [count]

### Project Status: READY FOR RELEASE

---

IMPORTANT:
- README.md must be user-friendly and comprehensive
- Example skill package must be complete and realistic
- All documentation must be accurate (no placeholder content)
- Verify ALL quality checks pass before declaring ready for release
- Project must be genuinely ready for public release

---

CONGRATULATIONS!
If all validation passes, the Composer AI Agent Skill Plugin is complete and ready for release!
```

---

## Progress Tracking Template

Create this file to track overall progress:

**File:** `PROGRESS.md`

```markdown
# Implementation Progress: Composer AI Agent Skill Plugin

**Started:** [date]
**Current Phase:** [phase number]
**Status:** [IN_PROGRESS/BLOCKED/COMPLETED]

---

## Phase 1: Project Foundation - [STATUS]
[Details filled by agent]

## Phase 2: Core Components - [STATUS]
[Details filled by agent]

## Phase 3: Commands & Integration - [STATUS]
[Details filled by agent]

## Phase 4: Testing & Quality - [STATUS]
[Details filled by agent]

## Phase 5: Documentation & Release - [STATUS]
[Details filled by agent]

---

## Overall Project Status

### Completed Milestones:
- [ ] Phase 1 Complete
- [ ] Phase 2 Complete
- [ ] Phase 3 Complete
- [ ] Phase 4 Complete
- [ ] Phase 5 Complete

### Current Blockers:
[None/List blockers]

### Quality Metrics:
- Test Coverage: [percentage]
- PHPStan Level: [level]
- Passing Tests: [count/total]

---

## Release Readiness: [YES/NO/PENDING]
```

---

## Usage Instructions for Agents

### Starting Fresh Project

```
1. Create PROGRESS.md from template above
2. Copy Phase 1 prompt and execute
3. Update PROGRESS.md with Phase 1 results
4. Proceed to Phase 2 only after Phase 1 validation passes
5. Repeat for all phases
```

### Resuming Work

```
1. Read PROGRESS.md to understand current state
2. Verify last completed phase validation
3. Continue with next phase prompt
4. Update PROGRESS.md after completion
```

### Handling Blockers

```
If validation fails:
1. Document issue in PROGRESS.md "Issues Encountered"
2. Fix the issue
3. Re-run validation
4. Only proceed when all checks pass
```

---

## Quality Standards Summary

Every phase must meet these standards:

- **Code Style:** PSR-12 compliant (PHP-CS-Fixer)
- **Static Analysis:** PHPStan level 8 with zero errors
- **Type Coverage:** 100% type hints on all methods
- **Strict Types:** declare(strict_types=1) in all files
- **Testing:** >80% code coverage by Phase 4
- **Documentation:** Clear, accurate, complete by Phase 5

---

**End of Agent Implementation Prompts**
