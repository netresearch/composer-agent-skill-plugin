# Agent Implementation Prompts

**Purpose:** Structured prompts for AI agents to implement the Composer AI Agent Skill Plugin
**Source Documents:** ../docs/PRD.md, ../WORKFLOW.md
**Last Updated:** 2025-11-24

---

## Overview

This directory contains phase-specific implementation prompts designed for AI agent execution. Each prompt guides an agent through a complete phase with:

- Clear prerequisites and validation
- Detailed task requirements
- Quality checkpoints
- Progress documentation templates
- Validation criteria

---

## Files in This Directory

| File | Description |
|------|-------------|
| `phase-1-project-foundation.md` | Project setup, composer.json, development tools |
| `phase-2-core-components.md` | Core plugin classes (SkillPlugin, SkillDiscovery, AgentsMdGenerator) |
| `phase-3-commands-integration.md` | CLI commands (list-skills, read-skill) |
| `phase-4-testing-quality.md` | Comprehensive testing and quality assurance |
| `phase-5-documentation-release.md` | Documentation and release preparation |
| `PROGRESS-TEMPLATE.md` | Template for tracking implementation progress |
| `README.md` | This file |

---

## Quick Start

### For Fresh Implementation

```bash
# 1. Copy progress template to project root
cp claudedocs/phase-prompts/PROGRESS-TEMPLATE.md PROGRESS.md

# 2. Start with Phase 1
# Copy the phase-1-project-foundation.md prompt and provide to agent

# 3. After Phase 1 completion, update PROGRESS.md

# 4. Continue with Phase 2-5 in order
```

### For Resuming Work

```bash
# 1. Check PROGRESS.md to see current phase

# 2. Validate previous phase completion

# 3. Provide next phase prompt to agent
```

---

## Usage Instructions

### Sequential Execution

**IMPORTANT:** Phases must be completed in order (1 → 5)

1. **Phase 1:** Must complete before Phase 2
2. **Phase 2:** Must complete before Phase 3
3. **Phase 3:** Must complete before Phase 4
4. **Phase 4:** Must complete before Phase 5
5. **Phase 5:** Final phase

### Validation Gates

Each phase has prerequisite validation that MUST pass before proceeding:

```bash
# Example validation before Phase 2:
- Check PROGRESS.md shows Phase 1 completed
- Verify composer.json exists and is valid
- Verify vendor/ directory exists
- Verify development tools installed
```

**If validation fails:** Fix issues and re-validate before proceeding.

### Progress Documentation

After completing each phase:

1. Update `PROGRESS.md` with completion status
2. Fill in validation results checklist
3. Document any issues encountered
4. Note any deviations from specifications

---

## Agent Prompt Structure

Each phase prompt follows this structure:

### 1. **Prerequisite Validation**
- Checks to run before starting
- Exit conditions if prerequisites not met

### 2. **Context**
- Reference documents to read
- Phase purpose and goals

### 3. **Requirements**
- Detailed task breakdown
- Specific implementation instructions
- Success criteria

### 4. **Validation Checklist**
- Commands to run
- Expected results
- Quality checks

### 5. **Progress Documentation**
- Template for PROGRESS.md updates
- Metrics to capture
- Status tracking

### 6. **Important Notes**
- Critical requirements
- Common pitfalls
- Blocking conditions

---

## Best Practices

### For Agents Executing Prompts

1. **Read Everything First**
   - Read entire prompt before starting
   - Review referenced documents (PRD.md, WORKFLOW.md)
   - Understand validation criteria

2. **Follow Order**
   - Complete tasks in specified order
   - Don't skip validation steps
   - Document as you go

3. **Validate Continuously**
   - Run validation checks after each task
   - Fix issues immediately
   - Don't accumulate technical debt

4. **Document Thoroughly**
   - Update PROGRESS.md after each task
   - Note all issues and resolutions
   - Record deviations with justification

5. **Quality First**
   - Meet all quality gates
   - Don't proceed with failing validation
   - Maintain code standards throughout

### For Humans Managing Agents

1. **Provide Complete Context**
   - Ensure agent has access to PRD.md and WORKFLOW.md
   - Provide phase prompt completely
   - Don't paraphrase or summarize

2. **Monitor Progress**
   - Review PROGRESS.md updates
   - Verify validation results
   - Check quality metrics

3. **Enforce Gates**
   - Don't allow skipping validations
   - Require issue resolution before proceeding
   - Maintain quality standards

4. **Review Phase Completion**
   - Review code against specifications
   - Run validation commands yourself
   - Confirm readiness for next phase

---

## Validation Commands

These commands are used throughout the phases:

### Code Style
```bash
./vendor/bin/php-cs-fixer fix
./vendor/bin/php-cs-fixer fix --dry-run  # Check without fixing
```

### Static Analysis
```bash
./vendor/bin/phpstan analyse
```

### Testing
```bash
./vendor/bin/phpunit                    # Run all tests
./vendor/bin/phpunit --coverage-text    # With coverage report
./vendor/bin/phpunit tests/Unit         # Unit tests only
```

### Composer
```bash
composer validate                       # Validate composer.json
composer install                        # Install dependencies
```

### File Structure
```bash
find . -type d                         # List directories
tree -L 3                              # Show structure (if tree installed)
```

---

## Quality Standards

All phases must meet these standards:

| Standard | Tool | Requirement |
|----------|------|-------------|
| Code Style | PHP-CS-Fixer | PSR-12 compliant |
| Static Analysis | PHPStan | Level 8, zero errors |
| Type Coverage | Manual review | 100% type hints |
| Strict Types | Manual review | All files have `declare(strict_types=1)` |
| Test Coverage | PHPUnit | >80% by Phase 4 |
| Documentation | Manual review | Complete by Phase 5 |

---

## Troubleshooting

### Phase Won't Validate

**Problem:** Validation checks failing
**Solution:**
1. Read error messages carefully
2. Fix identified issues
3. Re-run validation
4. Don't proceed until all checks pass

### Test Failures

**Problem:** PHPUnit tests failing
**Solution:**
1. Run tests individually to isolate issue
2. Check test output for failure details
3. Fix code or test as appropriate
4. Ensure all tests pass before proceeding

### PHPStan Errors

**Problem:** Static analysis errors
**Solution:**
1. Read error carefully to understand issue
2. Add missing type hints
3. Fix type mismatches
4. Run analysis again
5. Target: zero errors

### Can't Find Phase Prompt

**Problem:** Lost track of current phase
**Solution:**
1. Check PROGRESS.md for current status
2. Locate appropriate phase-X file in this directory
3. Validate previous phase before proceeding

---

## Phase-Specific Notes

### Phase 1: Project Foundation
- Simplest phase, establishes foundation
- Must complete fully before Phase 2
- Verify all tools accessible

### Phase 2: Core Components
- Most complex phase
- Reference PRD.md extensively for specifications
- XML format must match exactly
- Edge case handling is critical

### Phase 3: Commands & Integration
- Straightforward if Phase 2 solid
- Output formats must match PRD.md exactly
- Can't fully test until plugin installed

### Phase 4: Testing & Quality
- Comprehensive test coverage required
- >80% coverage target
- All quality gates must pass

### Phase 5: Documentation & Release
- Documentation must be accurate
- Example package must work
- Final validation before release

---

## Success Criteria

### Phase Complete When:
- All tasks finished
- All validation checks pass
- PROGRESS.md updated
- No blocking issues

### Project Complete When:
- All 5 phases done
- Release readiness checklist complete
- Documentation comprehensive
- Ready for Packagist submission

---

## Support

If you encounter issues:

1. **Check PROGRESS.md** - Review what's been completed
2. **Re-read prompt** - Ensure requirements understood
3. **Check PRD.md** - Verify specifications
4. **Run validation** - Identify specific failures
5. **Fix and retry** - Resolve issues systematically

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-11-24 | Initial prompt creation |

---

**Ready to begin? Start with phase-1-project-foundation.md!**
