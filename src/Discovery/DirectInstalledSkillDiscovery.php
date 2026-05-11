<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Discovery;

use Composer\IO\IOInterface;
use Netresearch\ComposerAgentSkillPlugin\Lock\SkillLockIo;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use Netresearch\ComposerAgentSkillPlugin\Trust\TrustState;
use Netresearch\ComposerAgentSkillPlugin\Util\SkillMarkdownParser;

/**
 * Skills materialized under vendor/agent-skills/installed from composer.skills.lock.
 *
 * @phpstan-import-type SkillRow from \Netresearch\ComposerAgentSkillPlugin\SkillGate
 */
final class DirectInstalledSkillDiscovery
{
    /**
     * @return list<array{name: string, description: string, location: string, package: string, version: string, file: string, trust_state: TrustState}>
     */
    public function discoverInstalled(IOInterface $io, SkillTrustManager $trust, string $projectRoot): array
    {
        unset($io);
        $lockIo = new SkillLockIo($projectRoot);
        $lock = $lockIo->read();
        if ($lock === null) {
            return [];
        }

        $out = [];
        foreach ($lock->packages as $pkg) {
            $installAbs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pkg->installPath);
            $skillFile = $installAbs . DIRECTORY_SEPARATOR . 'SKILL.md';
            if (!is_file($skillFile)) {
                continue;
            }
            $parsed = SkillMarkdownParser::parseNameDescription($skillFile);
            if ($parsed === null) {
                continue;
            }
            $trustKey = sprintf('direct:%s/%s', $pkg->source, $pkg->name);
            $trustState = TrustState::Pending;
            if ($trust->hasDecision($trustKey)) {
                $trustState = $trust->isAllowed($trustKey) ? TrustState::Allowed : TrustState::Denied;
            }

            $pin = ($pkg->ref ?? 'pin') . '@' . substr($pkg->commit, 0, 8);
            if ($pkg->type === 'path') {
                $pin = 'path@' . substr($pkg->checksum, 7, 12);
            }

            $realLoc = realpath($installAbs);
            $out[] = [
                'name' => $parsed['name'],
                'description' => $parsed['description'],
                'location' => str_replace(DIRECTORY_SEPARATOR, '/', $realLoc !== false ? $realLoc : $installAbs),
                'package' => $trustKey,
                'version' => $pin,
                'file' => 'SKILL.md',
                'trust_state' => $trustState,
                'direct_source' => $pkg->source,
                'direct_pin' => $pin,
            ];
        }

        return $out;
    }
}
