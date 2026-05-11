<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Integration;

use Composer\Console\Application;
use Netresearch\ComposerAgentSkillPlugin\Commands\SkillsCommand;
use Netresearch\ComposerAgentSkillPlugin\Tests\Support\RegistersConsoleCommands;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Live Git + GitHub (requires outbound HTTPS and git in PATH).
 *
 * Skip locally or in air-gapped CI with: SKIP_NETWORK_TESTS=1
 */
#[Group('network')]
#[Large]
final class DirectSkillsNetworkTest extends TestCase
{
    use RegistersConsoleCommands;

    private string $projectRoot;
    private string $cwdBackup;

    protected function setUp(): void
    {
        if (getenv('SKIP_NETWORK_TESTS') === '1') {
            self::markTestSkipped('SKIP_NETWORK_TESTS=1');
        }
        $this->projectRoot = sys_get_temp_dir() . '/net-ds-' . bin2hex(random_bytes(8));
        mkdir($this->projectRoot, 0777, true);
        file_put_contents(
            $this->projectRoot . '/composer.json',
            json_encode(['name' => 'test/network-direct-skills'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n",
        );
        $this->cwdBackup = (string) getcwd();
        chdir($this->projectRoot);
    }

    protected function tearDown(): void
    {
        chdir($this->cwdBackup);
        $this->rmTree($this->projectRoot);
    }

    public function testSkillsAddClonesPublicRepoAndWritesLock(): void
    {
        $cmd = new SkillsCommand('add');
        $app = new Application();
        self::registerCommand($app, $cmd);
        $tester = new CommandTester($cmd);

        $exit = $tester->execute([
            'source' => 'vercel-labs/skills',
            '--skill' => ['find-skills'],
            '--ref' => 'main',
        ]);

        self::assertSame(0, $exit, $tester->getDisplay());
        self::assertFileExists($this->projectRoot . '/composer.skills.lock');
        self::assertFileExists($this->projectRoot . '/vendor/agent-skills/installed/find-skills/SKILL.md');

        $lock = json_decode((string) file_get_contents($this->projectRoot . '/composer.skills.lock'), true);
        self::assertIsArray($lock);
        self::assertArrayHasKey('content-hash', $lock);
        self::assertCount(1, $lock['packages']);
        self::assertSame('find-skills', $lock['packages'][0]['name']);
        self::assertSame('https://github.com/vercel-labs/skills.git', $lock['packages'][0]['url']);
    }

    public function testSkillsInstallIsIdempotentAfterAdd(): void
    {
        $this->runSuccessfulAdd();

        $install = new SkillsCommand('install');
        $app = new Application();
        self::registerCommand($app, $install);
        $t1 = new CommandTester($install);
        self::assertSame(0, $t1->execute([]));
        $t2 = new CommandTester($install);
        self::assertSame(0, $t2->execute([]));
        self::assertFileExists($this->projectRoot . '/vendor/agent-skills/installed/find-skills/SKILL.md');
    }

    public function testSkillsInstallFailsWhenLockMissing(): void
    {
        $this->runSuccessfulAdd();
        unlink($this->projectRoot . '/composer.skills.lock');

        $install = new SkillsCommand('install');
        $app = new Application();
        self::registerCommand($app, $install);
        $tester = new CommandTester($install);

        self::assertSame(2, $tester->execute([]));
        self::assertStringContainsString('composer.skills.lock', $tester->getDisplay());
    }

    public function testSkillsInstallFailsWhenLockStale(): void
    {
        $this->runSuccessfulAdd();

        $path = $this->projectRoot . '/composer.json';
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        /** @var array<string, mixed> $data */
        $extra = $data['extra'] ?? [];
        self::assertIsArray($extra);
        $block = $extra['ai-agent-skills'] ?? [];
        self::assertIsArray($block);
        $sources = $block['sources'] ?? [];
        self::assertIsArray($sources);
        self::assertIsArray($sources[0]);
        $sources[0]['ref'] = 'main-intent-changed-without-lock-refresh';
        $block['sources'] = $sources;
        $extra['ai-agent-skills'] = $block;
        $data['extra'] = $extra;
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n");

        $install = new SkillsCommand('install');
        $app = new Application();
        self::registerCommand($app, $install);
        $tester = new CommandTester($install);

        self::assertSame(2, $tester->execute([]));
        self::assertStringContainsString('not up to date', $tester->getDisplay());
    }

    public function testSkillsUpdateFailsOnInvalidGitRef(): void
    {
        $path = $this->projectRoot . '/composer.json';
        $data = [
            'name' => 'test/x',
            'extra' => [
                'ai-agent-skills' => [
                    'version' => 1,
                    'sources' => [
                        [
                            'name' => 'vercel-labs/skills',
                            'type' => 'github',
                            'url' => 'https://github.com/vercel-labs/skills.git',
                            'ref' => 'branch-that-does-not-exist-network-test-zzzz',
                            'skills' => ['find-skills'],
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n");

        $update = new SkillsCommand('update');
        $app = new Application();
        self::registerCommand($app, $update);
        $tester = new CommandTester($update);

        self::assertSame(1, $tester->execute([]));
        self::assertNotEmpty($tester->getDisplay());
    }

    public function testSkillsAddFailsOnUnreachableRepository(): void
    {
        $cmd = new SkillsCommand('add');
        $app = new Application();
        self::registerCommand($app, $cmd);
        $tester = new CommandTester($cmd);

        $exit = $tester->execute([
            'source' => 'https://github.com/netresearch/composer-agent-skill-plugin-repo-network-test-nonexistent.git',
            '--skill' => ['find-skills'],
            '--ref' => 'main',
        ]);

        self::assertSame(1, $exit, $tester->getDisplay());
    }

    public function testSkillsAddRejectsMissingSkillOption(): void
    {
        $cmd = new SkillsCommand('add');
        $app = new Application();
        self::registerCommand($app, $cmd);
        $tester = new CommandTester($cmd);

        $exit = $tester->execute([
            'source' => 'vercel-labs/skills',
            '--ref' => 'main',
        ]);

        self::assertSame(7, $exit);
    }

    private function runSuccessfulAdd(): void
    {
        $cmd = new SkillsCommand('add');
        $app = new Application();
        self::registerCommand($app, $cmd);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'source' => 'vercel-labs/skills',
            '--skill' => ['find-skills'],
            '--ref' => 'main',
        ]);
        self::assertSame(0, $exit, $tester->getDisplay());
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
