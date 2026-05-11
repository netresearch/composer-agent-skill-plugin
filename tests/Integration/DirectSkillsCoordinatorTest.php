<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Integration;

use Composer\IO\NullIO;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\DirectSkillsCoordinator;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\MissingSkillsLockException;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\StaleSkillsLockException;
use PHPUnit\Framework\TestCase;

/**
 * Filesystem-only flows for {@see DirectSkillsCoordinator} (no remote Git).
 */
final class DirectSkillsCoordinatorTest extends TestCase
{
    private string $fixtureDupA;
    private string $fixtureDupB;

    protected function setUp(): void
    {
        $base = dirname(__DIR__) . '/Fixtures/direct-skills';
        $this->fixtureDupA = $base . '/dup-a';
        $this->fixtureDupB = $base . '/dup-b';
    }

    public function testTwoPathSourcesInstallDistinctSkills(): void
    {
        $root = $this->makeTempProjectRoot();
        try {
            $alpha = $root . '/vendor-fixtures/alpha';
            $beta = $root . '/vendor-fixtures/beta';
            mkdir($alpha, 0777, true);
            mkdir($beta, 0777, true);
            file_put_contents($alpha . '/SKILL.md', $this->minimalSkillYaml('alpha-one', 'First path skill'));
            file_put_contents($beta . '/SKILL.md', $this->minimalSkillYaml('beta-two', 'Second path skill'));

            $this->writeComposerJson($root, [
                'version' => 1,
                'sources' => [
                    [
                        'name' => 'local/alpha',
                        'type' => 'path',
                        'path' => './vendor-fixtures/alpha',
                        'skills' => ['*'],
                    ],
                    [
                        'name' => 'local/beta',
                        'type' => 'path',
                        'path' => './vendor-fixtures/beta',
                        'skills' => ['*'],
                    ],
                ],
            ]);

            $io = new NullIO();
            $coord = new DirectSkillsCoordinator();
            $coord->updateFloating($io, $root);

            self::assertFileExists($root . '/composer.skills.lock');
            $lock = json_decode((string) file_get_contents($root . '/composer.skills.lock'), true);
            self::assertIsArray($lock);
            self::assertCount(2, $lock['packages']);
            $names = array_column($lock['packages'], 'name');
            sort($names);
            self::assertSame(['alpha-one', 'beta-two'], $names);

            self::assertFileExists($root . '/vendor/agent-skills/installed/alpha-one/SKILL.md');
            self::assertFileExists($root . '/vendor/agent-skills/installed/beta-two/SKILL.md');

            $coord->installPinned($io, $root);
            self::assertFileExists($root . '/vendor/agent-skills/installed/alpha-one/SKILL.md');
        } finally {
            $this->rmTree($root);
        }
    }

    public function testMissingLockThrowsOnInstallPinned(): void
    {
        $root = $this->makeTempProjectRoot();
        try {
            file_put_contents($root . '/composer.json', json_encode([
                'name' => 'test/t',
                'extra' => [
                    'ai-agent-skills' => [
                        'version' => 1,
                        'sources' => [
                            [
                                'name' => 'x',
                                'type' => 'path',
                                'path' => './p',
                                'skills' => ['*'],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

            mkdir($root . '/p', 0777, true);
            file_put_contents($root . '/p/SKILL.md', $this->minimalSkillYaml('orphan', 'Orphan'));

            $coord = new DirectSkillsCoordinator();
            $this->expectException(MissingSkillsLockException::class);
            $coord->installPinned(new NullIO(), $root);
        } finally {
            $this->rmTree($root);
        }
    }

    public function testStaleLockThrowsOnInstallPinned(): void
    {
        $root = $this->makeTempProjectRoot();
        try {
            $src = $root . '/src-skill';
            mkdir($src, 0777, true);
            file_put_contents($src . '/SKILL.md', $this->minimalSkillYaml('mutable', 'Changes after lock'));

            $this->writeComposerJson($root, [
                'version' => 1,
                'sources' => [
                    [
                        'name' => 'local/mutable',
                        'type' => 'path',
                        'path' => './src-skill',
                        'skills' => ['mutable'],
                    ],
                ],
            ]);

            $coord = new DirectSkillsCoordinator();
            $coord->updateFloating(new NullIO(), $root);

            $data = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($data);
            /** @var array<string, mixed> $data */
            $extra = $data['extra'];
            self::assertIsArray($extra);
            $block = $extra['ai-agent-skills'];
            self::assertIsArray($block);
            $sources = $block['sources'];
            self::assertIsArray($sources);
            self::assertIsArray($sources[0]);
            // Mutate intent without regenerating lock — changes content-hash.
            $sources[0]['path'] = './src-skill-changed';
            $block['sources'] = $sources;
            $extra['ai-agent-skills'] = $block;
            $data['extra'] = $extra;
            file_put_contents($root . '/composer.json', json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            $this->expectException(StaleSkillsLockException::class);
            $coord->installPinned(new NullIO(), $root);
        } finally {
            $this->rmTree($root);
        }
    }

    public function testDuplicateSkillNameAcrossPathSourcesThrows(): void
    {
        $root = $this->makeTempProjectRoot();
        try {
            $a = $root . '/dups/a';
            $b = $root . '/dups/b';
            mkdir($a, 0777, true);
            mkdir($b, 0777, true);
            copy($this->fixtureDupA . '/SKILL.md', $a . '/SKILL.md');
            copy($this->fixtureDupB . '/SKILL.md', $b . '/SKILL.md');

            $this->writeComposerJson($root, [
                'version' => 1,
                'sources' => [
                    [
                        'name' => 'dup-src-a',
                        'type' => 'path',
                        'path' => './dups/a',
                        'skills' => ['*'],
                    ],
                    [
                        'name' => 'dup-src-b',
                        'type' => 'path',
                        'path' => './dups/b',
                        'skills' => ['*'],
                    ],
                ],
            ]);

            $coord = new DirectSkillsCoordinator();
            $this->expectException(DirectSkillsException::class);
            $this->expectExceptionMessage('Duplicate skill name');
            $coord->updateFloating(new NullIO(), $root);
        } finally {
            $this->rmTree($root);
        }
    }

    public function testPathSourceMissingDirectoryThrows(): void
    {
        $root = $this->makeTempProjectRoot();
        try {
            $this->writeComposerJson($root, [
                'version' => 1,
                'sources' => [
                    [
                        'name' => 'ghost',
                        'type' => 'path',
                        'path' => './does-not-exist',
                        'skills' => ['*'],
                    ],
                ],
            ]);

            $coord = new DirectSkillsCoordinator();
            $this->expectException(DirectSkillsException::class);
            $this->expectExceptionMessage('not found');
            $coord->updateFloating(new NullIO(), $root);
        } finally {
            $this->rmTree($root);
        }
    }

    private function makeTempProjectRoot(): string
    {
        $root = sys_get_temp_dir() . '/ds-coord-' . bin2hex(random_bytes(8));
        mkdir($root, 0777, true);

        return $root;
    }

    /**
     * @param array<string, mixed> $block ai-agent-skills body (without wrapping key)
     */
    private function writeComposerJson(string $root, array $block): void
    {
        $data = [
            'name' => 'test/direct-skills-coordinator',
            'extra' => [
                'ai-agent-skills' => $block,
            ],
        ];
        file_put_contents($root . '/composer.json', json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function minimalSkillYaml(string $name, string $description): string
    {
        return <<<MD
---
name: {$name}
description: {$description}
---

# Skill

MD;
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
        foreach ($it as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }
            $p = $fileInfo->getPathname();
            $fileInfo->isDir() ? @rmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
