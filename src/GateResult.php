<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin;

/**
 * Result of partitioning discovered skills by trust outcome.
 *
 * @phpstan-import-type SkillRow from SkillGate
 */
final readonly class GateResult
{
    /**
     * @param list<SkillRow>  $allowed Skills that should be registered in AGENTS.md.
     * @param list<string>    $denied  Distinct package names whose skills are denied.
     * @param list<string>    $pending Distinct package names whose skills are still pending.
     */
    public function __construct(
        public array $allowed,
        public array $denied,
        public array $pending,
    ) {
    }
}
