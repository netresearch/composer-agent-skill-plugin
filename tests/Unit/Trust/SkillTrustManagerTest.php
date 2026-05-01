<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Trust;

use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use PHPUnit\Framework\TestCase;

final class SkillTrustManagerTest extends TestCase
{
    private string $rootDir;
    private string $composerJsonPath;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/skill-trust-' . uniqid();
        mkdir($this->rootDir);
        $this->composerJsonPath = $this->rootDir . '/composer.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->composerJsonPath)) {
            unlink($this->composerJsonPath);
        }
        if (is_dir($this->rootDir)) {
            rmdir($this->rootDir);
        }
    }

    public function testReadsExplicitAllow(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => [
                'ai-agent-skill' => [
                    'allow-skills' => ['vendor/foo' => true],
                ],
            ],
        ]));
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);

        self::assertTrue($mgr->hasDecision('vendor/foo'));
        self::assertTrue($mgr->isAllowed('vendor/foo'));
    }

    public function testReadsExplicitDeny(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => false]]],
        ]));
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);

        self::assertTrue($mgr->hasDecision('vendor/foo'));
        self::assertFalse($mgr->isAllowed('vendor/foo'));
    }

    public function testGlobMatchAllowed(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['trusted-org/*' => true]]],
        ]));
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);

        self::assertTrue($mgr->hasDecision('trusted-org/some-skill'));
        self::assertTrue($mgr->isAllowed('trusted-org/some-skill'));
        self::assertFalse($mgr->hasDecision('other/pkg'));
    }

    public function testGlobMatchDenied(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['blocked-org/*' => false]]],
        ]));
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);

        self::assertTrue($mgr->hasDecision('blocked-org/some-pkg'));
        self::assertFalse($mgr->isAllowed('blocked-org/some-pkg'));
    }

    public function testNoConfigMeansNoDecision(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);

        self::assertFalse($mgr->hasDecision('vendor/foo'));
        self::assertFalse($mgr->isAllowed('vendor/foo'));
    }

    public function testMissingComposerJsonIsHandled(): void
    {
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
        self::assertFalse($mgr->hasDecision('any/pkg'));
    }

    public function testMalformedJsonIsHandled(): void
    {
        file_put_contents($this->composerJsonPath, '{ not valid json');
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);

        self::assertFalse($mgr->hasDecision('vendor/foo'));
    }

    public function testNonBooleanRulesAreIgnored(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => [
                'vendor/good' => true,
                'vendor/bad'  => 'yes', // wrong type — must be ignored
            ]]],
        ]));
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);

        self::assertTrue($mgr->hasDecision('vendor/good'));
        self::assertFalse($mgr->hasDecision('vendor/bad'));
    }

    public function testDecideRespectsExistingAllow(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => true]]],
        ]));
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);

        self::assertTrue($mgr->decide('vendor/foo'));
    }

    public function testDecideRespectsExistingDeny(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => false]]],
        ]));
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);

        self::assertFalse($mgr->decide('vendor/foo'));
    }

    public function testDecideNonInteractiveSkipsAndWarns(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $io = new BufferIO();
        $mgr = new SkillTrustManager($io, $this->rootDir);

        self::assertFalse($mgr->decide('vendor/new'));

        $output = $io->getOutput();
        self::assertStringContainsString('vendor/new', $output);
        self::assertStringContainsString('composer skills:trust', $output);
    }

    private function interactiveIo(string $answer): IOInterface
    {
        $io = $this->createStub(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('ask')->willReturn($answer);
        return $io;
    }

    public function testDecideInteractiveAllowPersistsToComposerJson(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $mgr = new SkillTrustManager($this->interactiveIo('y'), $this->rootDir);

        self::assertTrue($mgr->decide('vendor/foo'));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame(['vendor/foo' => true], $data['extra']['ai-agent-skill']['allow-skills']);
    }

    public function testDecideInteractiveDenyPersistsAsFalse(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $mgr = new SkillTrustManager($this->interactiveIo('n'), $this->rootDir);

        self::assertFalse($mgr->decide('vendor/bar'));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame(['vendor/bar' => false], $data['extra']['ai-agent-skill']['allow-skills']);
    }

    public function testDecideInteractiveSessionAllowDoesNotWriteFile(): void
    {
        $original = "{\n}\n";
        file_put_contents($this->composerJsonPath, $original);
        $mgr = new SkillTrustManager($this->interactiveIo('a'), $this->rootDir);

        self::assertTrue($mgr->decide('vendor/baz'));
        self::assertSame($original, file_get_contents($this->composerJsonPath));
        self::assertTrue($mgr->isAllowed('vendor/baz')); // session cache
    }

    public function testDecideInteractiveDiscardDoesNotWriteOrAllow(): void
    {
        $original = "{\n}\n";
        file_put_contents($this->composerJsonPath, $original);
        $mgr = new SkillTrustManager($this->interactiveIo('d'), $this->rootDir);

        self::assertFalse($mgr->decide('vendor/qux'));
        self::assertSame($original, file_get_contents($this->composerJsonPath));
        self::assertFalse($mgr->isAllowed('vendor/qux'));
    }

    public function testDecidePersistAddsToExistingMap(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/old' => true]]],
        ], JSON_PRETTY_PRINT));
        $mgr = new SkillTrustManager($this->interactiveIo('y'), $this->rootDir);

        self::assertTrue($mgr->decide('vendor/new'));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame(
            ['vendor/old' => true, 'vendor/new' => true],
            $data['extra']['ai-agent-skill']['allow-skills'],
        );
    }

    public function testSeedIfAbsentWritesMap(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
        $mgr->seedIfAbsent(['vendor/legacy-a', 'vendor/legacy-b']);

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame(true, $data['extra']['ai-agent-skill']['allow-skills']['vendor/legacy-a']);
        self::assertSame(true, $data['extra']['ai-agent-skill']['allow-skills']['vendor/legacy-b']);
    }

    public function testSeedIfAbsentSkipsWhenMapPresent(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => true]]],
        ], JSON_PRETTY_PRINT));
        $original = (string) file_get_contents($this->composerJsonPath);

        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
        $mgr->seedIfAbsent(['vendor/should-not-appear']);

        self::assertSame($original, file_get_contents($this->composerJsonPath));
    }

    public function testRootPackageIsAlwaysAllowed(): void
    {
        // The user IS the root — never prompt for self-authorization.
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir, 'my-org/my-project');

        self::assertTrue($mgr->hasDecision('my-org/my-project'));
        self::assertTrue($mgr->isAllowed('my-org/my-project'));
        // decide() returns true without writing anything to composer.json (file is missing).
        self::assertTrue($mgr->decide('my-org/my-project'));
        self::assertFileDoesNotExist($this->composerJsonPath);
    }

    public function testRootPackageNullByDefault(): void
    {
        // Backward compat: existing constructor signature without root name is unchanged.
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
        self::assertFalse($mgr->hasDecision('any/pkg'));
    }

    public function testSeedIfAbsentWithNoLegacyPackagesDoesNothing(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $original = (string) file_get_contents($this->composerJsonPath);

        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
        $mgr->seedIfAbsent([]);

        self::assertSame($original, file_get_contents($this->composerJsonPath));
    }

    public function testPersistPreservesExistingStringSkillsValue(): void
    {
        // Root project is itself a skill provider with extra.ai-agent-skill = "SKILL.md".
        // Persisting allow-skills must NOT destroy that — migrate it under skills sub-key.
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => 'SKILL.md'],
        ], JSON_PRETTY_PRINT));

        $mgr = new SkillTrustManager($this->interactiveIo('y'), $this->rootDir);
        self::assertTrue($mgr->decide('vendor/foo'));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        // Original skills value preserved under the skills sub-key.
        self::assertSame(['SKILL.md'], $data['extra']['ai-agent-skill']['skills']);
        // New allow-skills present.
        self::assertSame(['vendor/foo' => true], $data['extra']['ai-agent-skill']['allow-skills']);
    }

    public function testPersistPreservesExistingArraySkillsValue(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['skills/a.md', 'skills/b.md']],
        ], JSON_PRETTY_PRINT));

        $mgr = new SkillTrustManager($this->interactiveIo('y'), $this->rootDir);
        self::assertTrue($mgr->decide('vendor/foo'));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame(['skills/a.md', 'skills/b.md'], $data['extra']['ai-agent-skill']['skills']);
        self::assertSame(['vendor/foo' => true], $data['extra']['ai-agent-skill']['allow-skills']);
    }

    public function testWriteFailureEmitsWarning(): void
    {
        // Make the rootDir read-only so the atomic-write temp file creation fails.
        // (Just chmod-ing composer.json isn't enough — rename can still replace a
        // read-only target if the directory is writable.)
        file_put_contents($this->composerJsonPath, "{\n}\n");
        chmod($this->rootDir, 0o555);

        $io = $this->createStub(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('ask')->willReturn('y');
        $messages = [];
        $io->method('writeError')->willReturnCallback(function (string|array $msg) use (&$messages): void {
            $messages[] = is_array($msg) ? implode("\n", $msg) : $msg;
        });

        $mgr = new SkillTrustManager($io, $this->rootDir);
        $mgr->decide('vendor/foo');

        // Restore writability before tearDown unlinks.
        chmod($this->rootDir, 0o755);

        $combined = implode("\n", $messages);
        self::assertStringContainsString('Failed to write', $combined);
        self::assertStringContainsString('composer.json', $combined);
    }

    public function testPersistPreservesExistingObjectFormSkills(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => [
                'ai-agent-skill' => [
                    'skills' => ['skills/a.md'],
                    'something-else' => 'value',
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $mgr = new SkillTrustManager($this->interactiveIo('y'), $this->rootDir);
        self::assertTrue($mgr->decide('vendor/foo'));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame(['skills/a.md'], $data['extra']['ai-agent-skill']['skills']);
        self::assertSame('value', $data['extra']['ai-agent-skill']['something-else']);
        self::assertSame(['vendor/foo' => true], $data['extra']['ai-agent-skill']['allow-skills']);
    }
}
