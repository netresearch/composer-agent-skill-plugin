<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Util;

use Netresearch\ComposerAgentSkillPlugin\Trust\TrustState;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Merges Composer-package skills with direct-install skills (same order as callers used:
 * package list first, then direct). Duplicate names keep the first entry and emit a note.
 */
final class DiscoveredSkills
{
    /**
     * @param list<array{name: string, description: string, location: string, package: string, version: string, file: string, trust_state: TrustState}> $packageSkills
     * @param list<array{name: string, description: string, location: string, package: string, version: string, file: string, trust_state: TrustState}> $directSkills
     *
     * @return list<array{name: string, description: string, location: string, package: string, version: string, file: string, trust_state: TrustState}>
     */
    public static function mergePreferringPackageOrder(
        array $packageSkills,
        array $directSkills,
        OutputInterface $output,
    ): array {
        $merged = array_merge($packageSkills, $directSkills);
        $byName = [];
        foreach ($merged as $skill) {
            $name = $skill['name'];
            if (!isset($byName[$name])) {
                $byName[$name] = $skill;

                continue;
            }
            $kept = $byName[$name];
            $output->writeln(sprintf(
                '<comment>[NOTE] Duplicate skill name "%s": using %s v%s; skipping %s v%s.</comment>',
                $name,
                $kept['package'],
                $kept['version'],
                $skill['package'],
                $skill['version'],
            ));
        }

        return array_values($byName);
    }
}
