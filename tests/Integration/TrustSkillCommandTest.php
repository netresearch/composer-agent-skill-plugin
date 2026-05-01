<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Integration;

use Composer\Console\Application;
use Netresearch\ComposerAgentSkillPlugin\Commands\TrustSkillCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class TrustSkillCommandTest extends TestCase
{
    private string $rootDir;
    private string $cwdBackup;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/skills-trust-cmd-' . uniqid();
        mkdir($this->rootDir);
        file_put_contents($this->rootDir . '/composer.json', "{\n}\n");
        $this->cwdBackup = (string) getcwd();
        chdir($this->rootDir);
    }

    protected function tearDown(): void
    {
        chdir($this->cwdBackup);
        $cj = $this->rootDir . '/composer.json';
        if (file_exists($cj)) {
            unlink($cj);
        }
        if (is_dir($this->rootDir)) {
            rmdir($this->rootDir);
        }
    }

    public function testAllowsPackage(): void
    {
        $command = new TrustSkillCommand();
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($command);

        $exit = $tester->execute(['package' => 'vendor/foo']);

        self::assertSame(0, $exit);
        $data = json_decode((string) file_get_contents($this->rootDir . '/composer.json'), true);
        self::assertSame(true, $data['extra']['ai-agent-skill']['allow-skills']['vendor/foo']);
        self::assertStringContainsString('Allowed', $tester->getDisplay());
    }

    public function testDeniesPackageWithFlag(): void
    {
        $command = new TrustSkillCommand();
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($command);

        $exit = $tester->execute(['package' => 'vendor/bar', '--deny' => true]);

        self::assertSame(0, $exit);
        $data = json_decode((string) file_get_contents($this->rootDir . '/composer.json'), true);
        self::assertSame(false, $data['extra']['ai-agent-skill']['allow-skills']['vendor/bar']);
        self::assertStringContainsString('Denied', $tester->getDisplay());
    }

    public function testRevokeRemovesEntry(): void
    {
        file_put_contents($this->rootDir . '/composer.json', (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => [
                'vendor/foo' => true,
                'vendor/bar' => false,
            ]]],
        ], JSON_PRETTY_PRINT));

        $command = new TrustSkillCommand();
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($command);

        $exit = $tester->execute(['package' => 'vendor/foo', '--revoke' => true]);

        self::assertSame(0, $exit);
        $data = json_decode((string) file_get_contents($this->rootDir . '/composer.json'), true);
        self::assertArrayNotHasKey('vendor/foo', $data['extra']['ai-agent-skill']['allow-skills']);
        self::assertSame(false, $data['extra']['ai-agent-skill']['allow-skills']['vendor/bar']);
        self::assertStringContainsString('Revoked', $tester->getDisplay());
    }

    public function testDenyAndRevokeAreMutuallyExclusive(): void
    {
        $command = new TrustSkillCommand();
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($command);

        $exit = $tester->execute(['package' => 'vendor/foo', '--deny' => true, '--revoke' => true]);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('mutually exclusive', $tester->getDisplay());
    }
}
