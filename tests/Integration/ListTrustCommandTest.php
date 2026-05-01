<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Integration;

use Composer\Console\Application;
use Netresearch\ComposerAgentSkillPlugin\Commands\ListTrustCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ListTrustCommandTest extends TestCase
{
    private string $rootDir;
    private string $cwdBackup;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/list-trust-' . uniqid();
        mkdir($this->rootDir);
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

    public function testEmptyMessageWhenNoMap(): void
    {
        file_put_contents($this->rootDir . '/composer.json', "{\n}\n");

        $command = new ListTrustCommand();
        (new Application())->add($command);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No trust decisions', $tester->getDisplay());
    }

    public function testListsAllowedAndDeniedEntries(): void
    {
        file_put_contents($this->rootDir . '/composer.json', (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => [
                'vendor/foo' => true,
                'vendor/bar' => false,
                'trusted-org/*' => true,
            ]]],
        ], JSON_PRETTY_PRINT));

        $command = new ListTrustCommand();
        (new Application())->add($command);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('vendor/foo', $output);
        self::assertStringContainsString('[allowed]', $output);
        self::assertStringContainsString('vendor/bar', $output);
        self::assertStringContainsString('[denied]', $output);
        self::assertStringContainsString('trusted-org/*', $output);
    }
}
