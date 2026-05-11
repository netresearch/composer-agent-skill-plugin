<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\DirectSkills;

use Composer\IO\IOInterface;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\MissingSkillsLockException;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\StaleSkillsLockException;
use Netresearch\ComposerAgentSkillPlugin\Discovery\FilesystemSkillDiscovery;
use Netresearch\ComposerAgentSkillPlugin\Installer\SkillDirectoryCopier;
use Netresearch\ComposerAgentSkillPlugin\Installer\SkillDirectoryHasher;
use Netresearch\ComposerAgentSkillPlugin\Lock\LockedSkillPackage;
use Netresearch\ComposerAgentSkillPlugin\Lock\SkillLockFile;
use Netresearch\ComposerAgentSkillPlugin\Lock\SkillLockIo;
use Netresearch\ComposerAgentSkillPlugin\Source\ResolvedSource;
use Netresearch\ComposerAgentSkillPlugin\Source\SourceResolver;
use Netresearch\ComposerAgentSkillPlugin\Util\ComposerJsonReader;
use Netresearch\ComposerAgentSkillPlugin\Util\FilesystemUtil;
use Netresearch\ComposerAgentSkillPlugin\Util\GitCli;
use Netresearch\ComposerAgentSkillPlugin\Util\GitSemverResolver;
use Netresearch\ComposerAgentSkillPlugin\Util\SkillMarkdownParser;

/**
 * Direct skill install (pinned) and update (floating) orchestration.
 */
final class DirectSkillsCoordinator
{
    public function __construct(
        private readonly SourceResolver $resolver = new SourceResolver(),
        private readonly DirectSkillsContentHasher $hasher = new DirectSkillsContentHasher(),
        private readonly FilesystemSkillDiscovery $discovery = new FilesystemSkillDiscovery(),
        private readonly SkillDirectoryCopier $copier = new SkillDirectoryCopier(),
        private readonly SkillDirectoryHasher $skillHasher = new SkillDirectoryHasher(),
    ) {
    }

    public function isDisabledByEnv(): bool
    {
        return getenv('COMPOSER_AGENT_SKILLS') === '0';
    }

    /**
     * POST_INSTALL: materialize vendor from composer.skills.lock only.
     *
     * @throws MissingSkillsLockException|StaleSkillsLockException|DirectSkillsException
     */
    public function installPinned(IOInterface $io, string $projectRoot): void
    {
        if ($this->isDisabledByEnv()) {
            return;
        }
        $composerPath = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
        $data = ComposerJsonReader::read($composerPath);
        if ($data === null) {
            return;
        }
        $extraRaw = $data['extra'] ?? null;
        /** @var array<string, mixed> $extra */
        $extra = is_array($extraRaw) ? $extraRaw : [];
        $cfg = DirectSkillsConfig::tryFromExtra($extra);
        if ($cfg === null || $cfg->isEmpty()) {
            return;
        }

        $lockIo = new SkillLockIo($projectRoot);
        $lock = $lockIo->read();
        if ($lock === null) {
            throw new MissingSkillsLockException(
                'composer.skills.lock is missing but extra.ai-agent-skills.sources is configured. Run "composer skills update".',
            );
        }

        $expectedHash = $this->hasher->hashFromExtraArray($extra);
        if ($lock->contentHash !== $expectedHash) {
            throw new StaleSkillsLockException(
                'composer.skills.lock is not up to date with extra.ai-agent-skills. Run "composer skills update" and commit composer.skills.lock.',
            );
        }

        foreach ($lock->packages as $pkg) {
            $this->materializePackage($io, $projectRoot, $cfg, $pkg);
        }
    }

    /**
     * POST_UPDATE: resolve refs, rebuild lock, install.
     */
    public function updateFloating(IOInterface $io, string $projectRoot): void
    {
        if ($this->isDisabledByEnv()) {
            return;
        }
        $composerPath = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
        $data = ComposerJsonReader::read($composerPath);
        if ($data === null) {
            return;
        }
        $extra = $data['extra'] ?? null;
        /** @var array<string, mixed> $extra */
        $extra = is_array($extra) ? $extra : [];
        $cfg = DirectSkillsConfig::tryFromExtra($extra);
        if ($cfg === null || $cfg->isEmpty()) {
            return;
        }

        $packages = [];
        $seenNames = [];
        foreach ($cfg->sources as $source) {
            $resolved = $this->resolveSourceEntry($source);
            $chunk = $this->resolvePackagesForSource($io, $projectRoot, $cfg, $source, $resolved);
            foreach ($chunk as $p) {
                if (isset($seenNames[$p->name])) {
                    throw new DirectSkillsException(sprintf(
                        'Duplicate skill name "%s" from multiple sources. Remove or rename one source.',
                        $p->name,
                    ));
                }
                $seenNames[$p->name] = true;
                $packages[] = $p;
            }
        }

        $hash = $this->hasher->hashConfig($cfg);
        $lock = new SkillLockFile(
            1,
            PluginVersion::detect(),
            $hash,
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            $packages,
        );
        $lockIo = new SkillLockIo($projectRoot);
        $lockIo->write($lock);

        foreach ($lock->packages as $pkg) {
            $this->materializePackage($io, $projectRoot, $cfg, $pkg);
        }
    }

    /**
     * @throws DirectSkillsException
     */
    private function materializePackage(IOInterface $io, string $projectRoot, DirectSkillsConfig $cfg, LockedSkillPackage $pkg): void
    {
        DirectSkillsPathGuard::assertLockRelativePosix('install-path', $pkg->installPath, false);
        DirectSkillsPathGuard::assertLockRelativePosix('path', $pkg->pathInSource, true);

        $installAbs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pkg->installPath);
        DirectSkillsPathGuard::assertResolvedUnderProject($projectRoot, $installAbs);

        if ($pkg->type === 'path') {
            $urlRel = (string) $pkg->url;
            DirectSkillsPathGuard::assertLockRelativePosix('url', $urlRel, false);
            $basePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $urlRel);
            $skillDir = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pkg->pathInSource);
            $skillDir = realpath($skillDir) ?: $skillDir;
            if (!is_dir($skillDir)) {
                throw new DirectSkillsException(sprintf('Path skill source missing: %s', $skillDir));
            }
            DirectSkillsPathGuard::assertResolvedUnderProject($projectRoot, $skillDir);
            $this->copier->copyInto($skillDir, $installAbs, $io);
            $sum = $this->skillHasher->hashSkillDirectory($installAbs);
            if ($sum !== $pkg->checksum) {
                throw new DirectSkillsException(sprintf('Checksum mismatch for skill %s after install.', $pkg->name));
            }

            return;
        }

        $url = $pkg->url;
        if ($url === null || $url === '') {
            throw new DirectSkillsException(sprintf('Locked package %s has no clone URL.', $pkg->name));
        }
        $cacheRoot = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cfg->cacheDir);
        $slug = substr(hash('sha256', $url), 0, 16);
        $repoDir = $cacheRoot . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . $pkg->commit;
        $this->ensureGitCheckout($io, $url, $pkg->commit, $repoDir);

        $inside = $repoDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pkg->pathInSource);
        $inside = realpath($inside) ?: $inside;
        if (!is_dir($inside)) {
            throw new DirectSkillsException(sprintf('Skill path not found in clone: %s', $pkg->pathInSource));
        }
        DirectSkillsPathGuard::assertResolvedUnderProject($projectRoot, $inside);
        $this->copier->copyInto($inside, $installAbs, $io);
        $sum = $this->skillHasher->hashSkillDirectory($installAbs);
        if ($sum !== $pkg->checksum) {
            throw new DirectSkillsException(sprintf('Checksum mismatch for skill %s.', $pkg->name));
        }
    }

    private function ensureGitCheckout(IOInterface $io, string $url, string $commit, string $repoDir): void
    {
        if (is_dir($repoDir . DIRECTORY_SEPARATOR . '.git')) {
            try {
                $head = GitCli::revParse($repoDir);
                if ($head === $commit) {
                    return;
                }
                GitCli::mustRun(['fetch', '--quiet', 'origin'], $repoDir);
                GitCli::mustRun(['checkout', '--quiet', $commit], $repoDir);

                return;
            } catch (\RuntimeException $e) {
                $io->writeError(sprintf('<comment>Refreshing git cache %s: %s</comment>', $repoDir, $e->getMessage()));
                FilesystemUtil::removeDirectoryTree($repoDir, $io);
            }
        }
        if (!is_dir(dirname($repoDir))) {
            mkdir(dirname($repoDir), FilesystemUtil::DIRECTORY_MODE, true);
        }
        if (is_dir($repoDir)) {
            FilesystemUtil::removeDirectoryTree($repoDir, $io);
        }
        $this->gitMustRun(['clone', '--quiet', $url, $repoDir]);
        $this->gitMustRun(['checkout', '--quiet', $commit], $repoDir);
    }

    /**
     * @param list<string> $args
     */
    private function gitMustRun(array $args, ?string $cwd = null, int $timeout = 600): string
    {
        try {
            return GitCli::mustRun($args, $cwd, $timeout);
        } catch (\RuntimeException $e) {
            throw new DirectSkillsException(trim($e->getMessage()), 0, $e);
        }
    }

    private function gitRevParseHead(string $repoDir): string
    {
        return $this->gitMustRun(['rev-parse', 'HEAD'], $repoDir);
    }

    private function resolveSourceEntry(SourceEntry $entry): ResolvedSource
    {
        if ($entry->type === 'path') {
            $p = $entry->path;
            if ($p === null || $p === '') {
                throw new DirectSkillsException(sprintf('Path source "%s" needs a path.', $entry->name));
            }

            return new ResolvedSource($entry->name, 'path', null, null, $p);
        }
        $url = $entry->url ?? '';
        if ($url === '') {
            throw new DirectSkillsException(sprintf('Source "%s" needs a url.', $entry->name));
        }
        $ref = $entry->ref ?? 'main';

        return new ResolvedSource($entry->name, $entry->type === 'github' ? 'github' : 'git', $url, $ref, $entry->path);
    }

    /**
     * @return list<LockedSkillPackage>
     */
    private function resolvePackagesForSource(
        IOInterface $io,
        string $projectRoot,
        DirectSkillsConfig $cfg,
        SourceEntry $entry,
        ResolvedSource $resolved,
    ): array {
        if ($resolved->type === 'path') {
            return $this->lockFromPathSource($projectRoot, $cfg, $entry, $resolved);
        }

        $url = $resolved->url;
        if ($url === null) {
            throw new DirectSkillsException('Resolved git source without URL.');
        }
        $storedRef = $resolved->ref ?? 'main';
        $cloneRef = GitSemverResolver::resolveToGitRef($url, $storedRef);
        $cacheRoot = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cfg->cacheDir);
        $slug = substr(hash('sha256', $url), 0, 16);
        $workDir = $cacheRoot . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'work';
        FilesystemUtil::removeDirectoryTree($workDir, $io);
        if (!is_dir(dirname($workDir))) {
            mkdir(dirname($workDir), FilesystemUtil::DIRECTORY_MODE, true);
        }
        try {
            GitCli::mustRun(['clone', '--quiet', '--depth', '1', '--branch', $cloneRef, $url, $workDir]);
        } catch (\RuntimeException) {
            FilesystemUtil::removeDirectoryTree($workDir, $io);
            if (!is_dir(dirname($workDir))) {
                mkdir(dirname($workDir), FilesystemUtil::DIRECTORY_MODE, true);
            }
            $this->gitMustRun(['clone', '--quiet', $url, $workDir]);
            $this->gitMustRun(['checkout', '--quiet', $cloneRef], $workDir);
        }
        $commit = $this->gitRevParseHead($workDir);

        $baseInsideRepo = $workDir;
        if ($resolved->path !== null && $resolved->path !== '') {
            $baseInsideRepo .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $resolved->path);
        }
        $baseInsideRepo = realpath($baseInsideRepo);
        if ($baseInsideRepo === false || !is_dir($baseInsideRepo)) {
            throw new DirectSkillsException(sprintf('Source path not found after clone: %s', $resolved->path ?? '.'));
        }

        $found = $this->discovery->discoverFiltered($baseInsideRepo, $entry->skills);
        $out = [];
        foreach ($found as $skill) {
            $relDir = $skill['relativeSkillDir'];
            $skillAbs = $baseInsideRepo . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir === '.' ? '' : $relDir);
            $skillAbs = realpath($skillAbs);
            if ($skillAbs === false) {
                continue;
            }
            $pathInSource = $this->pathInSourceFromRoots($workDir, $skillAbs);
            $checksum = $this->skillHasher->hashSkillDirectory($skillAbs);
            $installRel = trim($cfg->installDir, '/') . '/' . $skill['name'];
            $meta = [];
            $desc = SkillMarkdownParser::parseNameDescription($skillAbs . DIRECTORY_SEPARATOR . 'SKILL.md');
            if ($desc !== null) {
                $meta['description'] = $desc['description'];
                $meta['schema'] = 'SKILL.md';
            }
            $out[] = new LockedSkillPackage(
                $skill['name'],
                $entry->name,
                $entry->type,
                $url,
                $storedRef,
                $commit,
                $pathInSource,
                $checksum,
                $installRel,
                $meta !== [] ? $meta : null,
            );
        }
        FilesystemUtil::removeDirectoryTree($workDir, $io);

        return $out;
    }

    private function pathInSourceFromRoots(string $repoRoot, string $skillDir): string
    {
        $repoRoot = realpath($repoRoot) ?: $repoRoot;
        $skillDir = realpath($skillDir) ?: $skillDir;
        $prefix = $repoRoot . DIRECTORY_SEPARATOR;
        if (!str_starts_with($skillDir . DIRECTORY_SEPARATOR, $prefix) && $skillDir !== $repoRoot) {
            return '.';
        }
        $rel = substr($skillDir, strlen($prefix));

        return str_replace(DIRECTORY_SEPARATOR, '/', $rel === '' ? '.' : $rel);
    }

    /**
     * @return list<LockedSkillPackage>
     */
    private function lockFromPathSource(
        string $projectRoot,
        DirectSkillsConfig $cfg,
        SourceEntry $entry,
        ResolvedSource $resolved,
    ): array {
        $rel = $resolved->path ?? '';
        $base = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $base = realpath($base);
        if ($base === false || !is_dir($base)) {
            throw new DirectSkillsException(sprintf('Local source path not found: %s', $rel));
        }
        $found = $this->discovery->discoverFiltered($base, $entry->skills);
        $out = [];
        foreach ($found as $skill) {
            $skillAbs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $skill['relativeSkillDir'] === '.' ? '' : $skill['relativeSkillDir']);
            $skillAbs = realpath($skillAbs);
            if ($skillAbs === false) {
                continue;
            }
            $pathInSource = str_replace('\\', '/', $skill['relativeSkillDir']);
            $checksum = $this->skillHasher->hashSkillDirectory($skillAbs);
            $installRel = trim($cfg->installDir, '/') . '/' . $skill['name'];
            $meta = [];
            $desc = SkillMarkdownParser::parseNameDescription($skillAbs . DIRECTORY_SEPARATOR . 'SKILL.md');
            if ($desc !== null) {
                $meta['description'] = $desc['description'];
                $meta['schema'] = 'SKILL.md';
            }
            $urlForLock = str_replace('\\', '/', $rel);
            $out[] = new LockedSkillPackage(
                $skill['name'],
                $entry->name,
                'path',
                $urlForLock,
                null,
                'local',
                $pathInSource,
                $checksum,
                $installRel,
                $meta !== [] ? $meta : null,
            );
        }

        return $out;
    }

    public function getResolver(): SourceResolver
    {
        return $this->resolver;
    }
}
