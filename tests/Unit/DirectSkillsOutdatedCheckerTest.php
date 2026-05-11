<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\DirectSkills\DirectSkillsOutdatedChecker;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\GitRemoteHeadLookup;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\PluginVersion;
use Netresearch\ComposerAgentSkillPlugin\Installer\SkillDirectoryHasher;
use Netresearch\ComposerAgentSkillPlugin\Lock\LockedSkillPackage;
use Netresearch\ComposerAgentSkillPlugin\Lock\SkillLockFile;
use Netresearch\ComposerAgentSkillPlugin\Lock\SkillLockIo;
use PHPUnit\Framework\TestCase;

final class DirectSkillsOutdatedCheckerTest extends TestCase
{
    public function testGitSkillOutdatedWhenRemoteCommitDiffers(): void
    {
        $root = sys_get_temp_dir() . '/skill-outdated-git-' . bin2hex(random_bytes(5));
        mkdir($root, 0777, true);
        try {
            $this->writeComposerWithSkills($root);
            $pkg = new LockedSkillPackage(
                's1',
                'gh/demo',
                'github',
                'https://example.com/repo.git',
                '^1.0',
                'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                '.',
                'sha256:unused',
                'vendor/agent-skills/installed/s1',
                null,
            );
            (new SkillLockIo($root))->write(new SkillLockFile(1, PluginVersion::detect(), 'x', '2026-01-01T00:00:00+00:00', [$pkg]));

            $stub = new class ('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb') implements GitRemoteHeadLookup {
                public function __construct(private readonly string $c)
                {
                }

                public function resolveRemoteCommit(string $url, string $storedRef): string
                {
                    return $this->c;
                }
            };
            $checker = new DirectSkillsOutdatedChecker($stub);
            $rows = $checker->collectOutdated($root, null);
            self::assertCount(1, $rows);
            self::assertSame('s1', $rows[0]['name']);
            self::assertSame('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $rows[0]['latest']);
        } finally {
            $this->rmTree($root);
        }
    }

    public function testGitSkillNotOutdatedWhenCommitsMatch(): void
    {
        $root = sys_get_temp_dir() . '/skill-outdated-ok-' . bin2hex(random_bytes(5));
        mkdir($root, 0777, true);
        try {
            $this->writeComposerWithSkills($root);
            $sha = 'cccccccccccccccccccccccccccccccccccccccc';
            $pkg = new LockedSkillPackage(
                's1',
                'gh/demo',
                'github',
                'https://example.com/repo.git',
                'main',
                $sha,
                '.',
                'sha256:unused',
                'vendor/agent-skills/installed/s1',
                null,
            );
            (new SkillLockIo($root))->write(new SkillLockFile(1, PluginVersion::detect(), 'x', '2026-01-01T00:00:00+00:00', [$pkg]));

            $stub = new class ($sha) implements GitRemoteHeadLookup {
                public function __construct(private readonly string $c)
                {
                }

                public function resolveRemoteCommit(string $url, string $storedRef): string
                {
                    return $this->c;
                }
            };
            $checker = new DirectSkillsOutdatedChecker($stub);
            self::assertSame([], $checker->collectOutdated($root, null));
        } finally {
            $this->rmTree($root);
        }
    }

    public function testPathSkillRejectsTamperedLockUrlWithTraversal(): void
    {
        $root = sys_get_temp_dir() . '/skill-outdated-badlock-' . bin2hex(random_bytes(5));
        mkdir($root, 0777, true);
        try {
            $this->writeComposerPathSource($root, './skills/mine');
            $pkg = new LockedSkillPackage(
                'mine',
                'local/x',
                'path',
                '../escape',
                null,
                'local',
                '.',
                'sha256:deadbeef',
                'vendor/agent-skills/installed/mine',
                null,
            );
            (new SkillLockIo($root))->write(new SkillLockFile(1, PluginVersion::detect(), 'x', '2026-01-01T00:00:00+00:00', [$pkg]));

            $this->expectException(DirectSkillsException::class);
            $this->expectExceptionMessage('url');
            (new DirectSkillsOutdatedChecker())->collectOutdated($root, null);
        } finally {
            $this->rmTree($root);
        }
    }

    public function testPathSkillOutdatedWhenChecksumChanges(): void
    {
        $root = sys_get_temp_dir() . '/skill-outdated-path-' . bin2hex(random_bytes(5));
        $skillDir = $root . '/skills/mine';
        mkdir($skillDir, 0777, true);
        try {
            file_put_contents(
                $skillDir . '/SKILL.md',
                "---\nname: mine\ndescription: d\n---\nbody\n",
            );
            $hasher = new SkillDirectoryHasher();
            $good = $hasher->hashSkillDirectory($skillDir);

            $this->writeComposerPathSource($root, './skills/mine');
            $pkg = new LockedSkillPackage(
                'mine',
                'local/x',
                'path',
                './skills/mine',
                null,
                'local',
                '.',
                'sha256:deadbeef',
                'vendor/agent-skills/installed/mine',
                null,
            );
            (new SkillLockIo($root))->write(new SkillLockFile(1, PluginVersion::detect(), 'x', '2026-01-01T00:00:00+00:00', [$pkg]));

            $checker = new DirectSkillsOutdatedChecker();
            $rows = $checker->collectOutdated($root, null);
            self::assertCount(1, $rows);
            self::assertSame($good, $rows[0]['latest']);
            self::assertSame('sha256:deadbeef', $rows[0]['installed']);
        } finally {
            $this->rmTree($root);
        }
    }

    private function writeComposerWithSkills(string $root): void
    {
        file_put_contents($root . '/composer.json', json_encode([
            'name' => 't/outdated',
            'extra' => [
                'ai-agent-skills' => [
                    'version' => 1,
                    'sources' => [
                        [
                            'name' => 'gh/demo',
                            'type' => 'github',
                            'url' => 'https://example.com/repo.git',
                            'ref' => 'main',
                            'skills' => ['s1'],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    private function writeComposerPathSource(string $root, string $path): void
    {
        file_put_contents($root . '/composer.json', json_encode([
            'name' => 't/pathout',
            'extra' => [
                'ai-agent-skills' => [
                    'version' => 1,
                    'sources' => [
                        [
                            'name' => 'local/x',
                            'type' => 'path',
                            'path' => $path,
                            'skills' => ['mine'],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $p = $f->getPathname();
            $f->isDir() ? @rmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
