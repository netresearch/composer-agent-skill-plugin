# Phase 3: Commands & Integration

I need you to implement Phase 3 (Commands & Integration) of the Composer AI Agent Skill Plugin as specified in WORKFLOW.md.

## PREREQUISITE VALIDATION

Before starting, verify Phase 2 completion:

1. Check PROGRESS.md shows Phase 2 completed
2. Verify src/SkillPlugin.php exists and implements required interfaces
3. Verify src/SkillDiscovery.php exists
4. Verify src/AgentsMdGenerator.php exists
5. Run: `./vendor/bin/phpstan analyse` - must pass
6. Run: `./vendor/bin/php-cs-fixer fix --dry-run` - must pass

**If any checks fail, STOP and complete Phase 2 first.**

## CONTEXT

- Read @WORKFLOW.md Phase 3 section completely
- Reference @PRD.md section "Commands" for exact specifications
- This phase implements CLI commands

## REQUIREMENTS

### Task 3.1: Implement ListSkillsCommand

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

### Task 3.2: Implement ReadSkillCommand

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

### Task 3.3: Register Commands

- Create src/CommandCapability.php
- Implement Composer\Plugin\Capability\CommandProvider
- Implement getCommands() method returning array of command instances
- Return [new ListSkillsCommand(), new ReadSkillCommand()]
- Verify SkillPlugin::getCapabilities() returns CommandProvider::class => CommandCapability::class
- Add strict_types declaration

## VALIDATION CHECKLIST

After completing all tasks:

1. Run: `./vendor/bin/php-cs-fixer fix` - must pass
2. Run: `./vendor/bin/phpstan analyse` - must pass with zero errors
3. Verify command registration in SkillPlugin
4. Verify CommandCapability returns both commands
5. Check output formats match PRD.md EXACTLY
6. Verify error handling in ReadSkillCommand

## MANUAL TESTING

Since plugin is not yet installed, we can't test commands directly. Instead:

1. Review code against PRD.md specifications
2. Verify SkillDiscovery integration
3. Verify output format strings match PRD.md
4. Confirm error messages match PRD.md

## PROGRESS DOCUMENTATION

Update PROGRESS.md with:

```markdown
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
```

---

## IMPORTANT

- Output formats MUST match PRD.md EXACTLY (see "Commands" section)
- ListSkillsCommand: columnar format, no table borders, sorted alphabetically
- ReadSkillCommand: openskills format with header, content, footer
- Do NOT proceed to Phase 4 until ALL validation checks pass
