<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\DirectSkills;

use Composer\IO\IOInterface;
use Netresearch\ComposerAgentSkillPlugin\Installer\SkillDirectoryHasher;
use Netresearch\ComposerAgentSkillPlugin\Lock\LockedSkillPackage;
use Netresearch\ComposerAgentSkillPlugin\Lock\SkillLockIo;
use Netresearch\ComposerAgentSkillPlugin\Util\ComposerJsonReader;
use Netresearch\ComposerAgentSkillPlugin\Util\GitRefCommitResolver;

/**
 * Compares composer.skills.lock to remote heads / local path hashes to find updatable direct skills.
 */
final class DirectSkillsOutdatedChecker
{
    /** @var array<string, string> */
    private array $remoteCommitCache = [];

    public function __construct(
        private readonly GitRemoteHeadLookup $remoteLookup = new GitRefCommitResolver(),
        private readonly SkillDirectoryHasher $skillHasher = new SkillDirectoryHasher(),
    ) {
    }

    /**
     * @return list<array{name: string, source: string, type: string, url: ?string, ref: ?string, installed: string, latest: string}>
     */
    public function collectOutdated(string $projectRoot, ?IOInterface $io = null): array
    {
        $this->remoteCommitCache = [];
        $composerPath = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
        $data = ComposerJsonReader::read($composerPath);
        if ($data === null) {
            return [];
        }
        $extra = $data['extra'] ?? [];
        /** @var array<string, mixed> $extra */
        $extra = is_array($extra) ? $extra : [];
        $cfg = DirectSkillsConfig::tryFromExtra($extra);
        if ($cfg === null || $cfg->isEmpty()) {
            return [];
        }

        $lockIo = new SkillLockIo($projectRoot);
        $lock = $lockIo->read();
        if ($lock === null) {
            return [];
        }

        $out = [];
        foreach ($lock->packages as $pkg) {
            $row = $this->diffPackage($projectRoot, $pkg, $io);
            if ($row !== null) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @return ?array{name: string, source: string, type: string, url: ?string, ref: ?string, installed: string, latest: string}
     */
    private function diffPackage(string $projectRoot, LockedSkillPackage $pkg, ?IOInterface $io): ?array
    {
        if ($pkg->type === 'path') {
            return $this->diffPathPackage($projectRoot, $pkg);
        }
        if ($pkg->url === null || $pkg->url === '') {
            return null;
        }
        $ref = $pkg->ref ?? 'main';
        try {
            $cacheKey = $pkg->url . "\0" . $ref;
            if (!isset($this->remoteCommitCache[$cacheKey])) {
                $this->remoteCommitCache[$cacheKey] = $this->remoteLookup->resolveRemoteCommit($pkg->url, $ref);
            }
            $latest = $this->remoteCommitCache[$cacheKey];
        } catch (\Throwable $e) {
            if ($io !== null) {
                $io->writeError(sprintf(
                    '<comment>Skipping outdated check for skill %s (%s): %s</comment>',
                    $pkg->name,
                    $pkg->source,
                    $e->getMessage(),
                ));
            }

            return null;
        }
        if (GitRefCommitResolver::commitsEquivalent($pkg->commit, $latest)) {
            return null;
        }

        return [
            'name' => $pkg->name,
            'source' => $pkg->source,
            'type' => $pkg->type,
            'url' => $pkg->url,
            'ref' => $pkg->ref,
            'installed' => $pkg->commit,
            'latest' => $latest,
        ];
    }

    /**
     * @return ?array{name: string, source: string, type: string, url: ?string, ref: ?string, installed: string, latest: string}
     */
    private function diffPathPackage(string $projectRoot, LockedSkillPackage $pkg): ?array
    {
        $rel = $pkg->url ?? '';
        if ($rel === '') {
            return null;
        }
        $baseRel = str_replace('/', DIRECTORY_SEPARATOR, ltrim(str_replace('\\', '/', $rel), './'));
        $base = $projectRoot . DIRECTORY_SEPARATOR . $baseRel;
        $base = realpath($base);
        if ($base === false || !is_dir($base)) {
            return [
                'name' => $pkg->name,
                'source' => $pkg->source,
                'type' => 'path',
                'url' => $pkg->url,
                'ref' => null,
                'installed' => $pkg->checksum,
                'latest' => '(missing path)',
            ];
        }
        $pi = $pkg->pathInSource === '.' ? '' : $pkg->pathInSource;
        $skillDir = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pi);
        $skillDir = realpath($skillDir);
        if ($skillDir === false || !is_dir($skillDir)) {
            return [
                'name' => $pkg->name,
                'source' => $pkg->source,
                'type' => 'path',
                'url' => $pkg->url,
                'ref' => null,
                'installed' => $pkg->checksum,
                'latest' => '(missing path)',
            ];
        }
        try {
            $now = $this->skillHasher->hashSkillDirectory($skillDir);
        } catch (\Throwable) {
            return null;
        }
        if ($now === $pkg->checksum) {
            return null;
        }

        return [
            'name' => $pkg->name,
            'source' => $pkg->source,
            'type' => 'path',
            'url' => $pkg->url,
            'ref' => null,
            'installed' => $pkg->checksum,
            'latest' => $now,
        ];
    }
}
