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
            $state = $skill['trust_state'];
            if ($state === TrustState::Pending) {
                // Only place that may prompt. decide() returns the resolved
                // TrustState, so callers don't second-guess.
                $state = $this->trust->decide($skill['package']);
            }

            match ($state) {
                TrustState::Allowed => $allowed[] = $skill,
                TrustState::Denied  => $deniedPackages[$skill['package']] = true,
                TrustState::Pending => $pendingPackages[$skill['package']] = true,
            };
        }

        return new GateResult(
            allowed: $allowed,
            denied: array_keys($deniedPackages),
            pending: array_keys($pendingPackages),
        );
    }
}
