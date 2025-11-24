# Phase 2: Core Components

I need you to implement Phase 2 (Core Components) of the Composer AI Agent Skill Plugin as specified in WORKFLOW.md.

## PREREQUISITE VALIDATION

Before starting, verify Phase 1 completion:

1. Check PROGRESS.md shows Phase 1 completed
2. Verify composer.json exists and is valid
3. Verify vendor/ directory exists
4. Verify development tools are installed

**If any checks fail, STOP and complete Phase 1 first.**

## CONTEXT

- Read @WORKFLOW.md Phase 2 section completely
- Reference @PRD.md sections: Technical Architecture, Package Discovery, Edge Case Handling
- This phase implements core plugin functionality

## REQUIREMENTS

### Task 2.1: Implement SkillPlugin (Main Plugin Class)

- Create src/SkillPlugin.php
- Implement PluginInterface, Capable, EventSubscriberInterface
- Add composer and io properties
- Implement activate(), deactivate(), uninstall() methods
- Implement getCapabilities() method
- Implement getSubscribedEvents() method (POST_INSTALL_CMD, POST_UPDATE_CMD)
- Create placeholder updateAgentsMd() method (implement in Task 2.4)
- Add strict_types declaration
- Ensure 100% type coverage

### Task 2.2: Implement SkillDiscovery

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

### Task 2.3: Implement AgentsMdGenerator

- Create src/AgentsMdGenerator.php
- Implement generateSkillsXml() method - returns XML string
- Implement updateAgentsMd() method - file operations
- Match EXACT openskills XML format from PRD.md
- Sort skills alphabetically by name
- Use regex to replace `<skills_system>` block only
- Preserve all other markdown content
- Create file if doesn't exist
- Implement atomic writes (temp file + rename)
- Add strict_types declaration

### Task 2.4: Wire Components Together

- Update SkillPlugin::updateAgentsMd() implementation
- Instantiate SkillDiscovery with $this->io
- Call discoverAllSkills()
- Instantiate AgentsMdGenerator
- Get project root with getcwd()
- Call updateAgentsMd() with AGENTS.md path and skills
- Add try-catch with error messages
- Output success message with skill count

## VALIDATION CHECKLIST

After completing all tasks:

1. Run: `./vendor/bin/php-cs-fixer fix` - must pass or fix issues
2. Run: `./vendor/bin/phpstan analyse` - must have zero errors
3. Verify all classes have declare(strict_types=1)
4. Verify all methods have type hints
5. Check SkillPlugin implements required interfaces
6. Check SkillDiscovery uses InstalledVersions API correctly
7. Check AgentsMdGenerator XML matches PRD.md exactly
8. Check error handling is graceful (warnings, not exceptions)

## PROGRESS DOCUMENTATION

Update PROGRESS.md with:

```markdown
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
```

---

## IMPORTANT

- Reference PRD.md for EXACT specifications (XML format, validation rules, error handling)
- Use Composer\InstalledVersions for package discovery (NOT manual vendor scanning)
- Implement ALL edge cases from PRD.md section "Edge Case Handling"
- XML must match openskills format EXACTLY (from PRD.md "AGENTS.md Format")
- Do NOT proceed to Phase 3 until ALL validation checks pass
