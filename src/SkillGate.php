<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin;

use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use Netresearch\ComposerAgentSkillPlugin\Trust\TrustState;

/**
 * Partitions discovered skills into allowed / denied / pending buckets.
 *
 * The only place where {@see SkillTrustManager::decide()} is invoked from
 * the install/update flow — this is the trust prompt boundary. Pure for
 * already-resolved (allowed/denied) entries; only triggers prompts on
 * pending entries.
 *
 * @phpstan-type SkillRow array{name: string, description: string, location: string, package: string, version: string, file: string, trust_state: TrustState}
 */
final class SkillGate
{
    public function __construct(
        private readonly SkillTrustManager $trust,
    ) {
    }

    /**
     * @param list<SkillRow> $allSkills
     */
    public function gate(array $allSkills): GateResult
    {
        $allowed = [];
        $deniedPackages = [];
        $pendingPackages = [];

        foreach ($allSkills as $skill) {
            switch ($skill['trust_state']) {
                case TrustState::Allowed:
                    $allowed[] = $skill;
                    break;
                case TrustState::Denied:
                    $deniedPackages[$skill['package']] = true;
                    break;
                case TrustState::Pending:
                    // Only place that may prompt. After decide() we re-classify
                    // so a freshly persisted 'n' (deny) lands in denied, not pending.
                    if ($this->trust->decide($skill['package'])) {
                        $allowed[] = $skill;
                    } elseif ($this->trust->hasDecision($skill['package']) && !$this->trust->isAllowed($skill['package'])) {
                        $deniedPackages[$skill['package']] = true;
                    } else {
                        $pendingPackages[$skill['package']] = true;
                    }
                    break;
            }
        }

        return new GateResult(
            allowed: $allowed,
            denied: array_keys($deniedPackages),
            pending: array_keys($pendingPackages),
        );
    }
}
