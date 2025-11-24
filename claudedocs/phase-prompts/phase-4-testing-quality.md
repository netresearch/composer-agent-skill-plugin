# Phase 4: Testing & Quality

I need you to implement Phase 4 (Testing & Quality) of the Composer AI Agent Skill Plugin as specified in WORKFLOW.md.

## PREREQUISITE VALIDATION

Before starting, verify Phase 3 completion:

1. Check PROGRESS.md shows Phase 3 completed
2. Verify src/Commands/ListSkillsCommand.php exists
3. Verify src/Commands/ReadSkillCommand.php exists
4. Verify src/CommandCapability.php exists
5. Run: `./vendor/bin/phpstan analyse` - must have zero errors
6. Run: `./vendor/bin/php-cs-fixer fix --dry-run` - must pass

**If any checks fail, STOP and complete Phase 3 first.**

## CONTEXT

- Read @WORKFLOW.md Phase 4 section completely
- Reference @PRD.md for edge case specifications
- This phase implements comprehensive test coverage

## REQUIREMENTS

### Task 4.1: Create Test Fixtures

- Create tests/Fixtures/ directory structure as specified in WORKFLOW.md
- Create valid-single-skill/ with proper composer.json and SKILL.md
- Create valid-multi-skill/ with composer.json (extra.ai-agent-skill array) and skills/
- Create invalid-frontmatter/ with SKILL.md missing description
- Create malformed-yaml/ with invalid YAML in SKILL.md
- Create missing-skill-file/ with only composer.json
- Create duplicate-names/ with two packages having same skill name
- Ensure all fixtures are realistic and follow PRD.md specifications

### Task 4.2: Unit Tests - SkillDiscovery

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

### Task 4.3: Unit Tests - AgentsMdGenerator

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

### Task 4.4: Integration Tests

- Create tests/Integration/ directory
- Create tests/Integration/PluginIntegrationTest.php
- Test full plugin lifecycle
- Test command execution
- Test AGENTS.md generation end-to-end
- Use test fixtures
- Target: >70% integration coverage

### Task 4.5: Run Quality Checks

- Run: `./vendor/bin/php-cs-fixer fix`
- Run: `./vendor/bin/phpstan analyse`
- Run: `./vendor/bin/phpunit`
- Run: `./vendor/bin/phpunit --coverage-text`
- Fix any issues found
- Ensure all tests pass
- Verify coverage >80%

## VALIDATION CHECKLIST

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

## PROGRESS DOCUMENTATION

Update PROGRESS.md with:

```markdown
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
```

---

## IMPORTANT

- Use realistic test fixtures that match PRD.md specifications
- Test ALL edge cases from PRD.md "Edge Case Handling" section
- Verify XML output matches openskills format EXACTLY
- Ensure >80% code coverage before proceeding
- All tests must pass - no skipped tests
- Do NOT proceed to Phase 5 until ALL validation checks pass
