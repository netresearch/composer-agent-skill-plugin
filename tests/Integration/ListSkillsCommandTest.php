<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Integration;

use Composer\Console\Application;
use Composer\IO\BufferIO;
use Netresearch\ComposerAgentSkillPlugin\Commands\ListSkillsCommand;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider;
use Netresearch\ComposerAgentSkillPlugin\SkillDiscovery;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ListSkillsCommandTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/list-skills-' . uniqid();
        mkdir($this->rootDir);
    }

    protected function tearDown(): void
    {
        $cj = $this->rootDir . '/composer.json';
        if (file_exists($cj)) {
            unlink($cj);
        }
        foreach ((array) glob($this->rootDir . '/composer.json.skill-trust.*') as $f) {
            if (is_string($f) && is_file($f)) {
                @unlink($f);
            }
        }
        if (is_dir($this->rootDir)) {
            rmdir($this->rootDir);
        }
        foreach ($this->dirsToCleanUp as $dir) {
            if (file_exists($dir . '/SKILL.md')) {
                unlink($dir . '/SKILL.md');
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
        $this->dirsToCleanUp = [];
    }

    public function testEmptyMessageWhenNoSkills(): void
    {
        $provider = new class () implements PackageProvider {
            public function iterAllPackages(): iterable
            {
                yield from [];
            }
        };
        $trust = SkillTrustManager::forComposerJson(new BufferIO(), $this->rootDir . '/composer.json');
        $discovery = new SkillDiscovery(new BufferIO(), $provider, $trust);

        $command = new ListSkillsCommand($discovery);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('No AI agent skills', $tester->getDisplay());
    }

    public function testShowsAllowedAndPendingColumns(): void
    {
        // Two distinct fixture dirs so the skill names differ.
        $allowedDir = $this->makeFixture('allowed-skill-name');
        $pendingDir = $this->makeFixture('pending-skill-name');

        $provider = new class ($allowedDir, $pendingDir) implements PackageProvider {
            public function __construct(private string $allowedPath, private string $pendingPath)
            {
            }

            public function iterAllPackages(): iterable
            {
                yield new PackageInfo('allowed/lib', $this->allowedPath, '1.0.0', 'library', ['ai-agent-skill' => 'SKILL.md']);
                yield new PackageInfo('pending/lib', $this->pendingPath, '1.0.0', 'library', ['ai-agent-skill' => 'SKILL.md']);
            }
        };

        file_put_contents($this->rootDir . '/composer.json', (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['allowed/lib' => true]]],
        ]));

        $trust = SkillTrustManager::forComposerJson(new BufferIO(), $this->rootDir . '/composer.json');
        $discovery = new SkillDiscovery(new BufferIO(), $provider, $trust);

        $command = new ListSkillsCommand($discovery);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $output = $tester->getDisplay();

        self::assertStringContainsString('allowed/lib', $output);
        self::assertStringContainsString('pending/lib', $output);
        self::assertStringContainsString('[allowed]', $output);
        self::assertStringContainsString('[pending]', $output);
        self::assertStringContainsString('1 pending', $output);
    }

    private function makeFixture(string $skillName): string
    {
        $dir = sys_get_temp_dir() . '/list-skills-fixture-' . uniqid();
        mkdir($dir);
        file_put_contents(
            $dir . '/SKILL.md',
            "---\nname: {$skillName}\ndescription: test fixture skill\n---\n\n# Test\n",
        );
        $this->dirsToCleanUp[] = $dir;
        return $dir;
    }

    /** @var list<string> */
    private array $dirsToCleanUp = [];
}
