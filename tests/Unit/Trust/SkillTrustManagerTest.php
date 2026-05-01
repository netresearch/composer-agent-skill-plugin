<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Trust;

use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider;
use Netresearch\ComposerAgentSkillPlugin\Trust\FirstRunInput;
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
        // The TrustStore deliberately keeps the lock sidecar — clean it up here.
        foreach ((array) glob($this->rootDir . '/composer.json.skill-trust.*') as $f) {
            if (is_string($f) && is_file($f)) {
                @unlink($f);
            }
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
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertTrue($mgr->hasDecision('vendor/foo'));
        self::assertTrue($mgr->isAllowed('vendor/foo'));
    }

    public function testReadsExplicitDeny(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => false]]],
        ]));
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertTrue($mgr->hasDecision('vendor/foo'));
        self::assertFalse($mgr->isAllowed('vendor/foo'));
    }

    public function testGlobMatchingIsCaseSensitive(): void
    {
        // Composer package names are normalized to lowercase. Allowing case-
        // insensitive matching means `acme/*: true` would also trust `Acme/Evil`
        // on private repos that don't normalize. Our matcher is case-sensitive.
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['acme/*' => true]]],
        ]));
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertTrue($mgr->isAllowed('acme/skill'));
        self::assertFalse($mgr->hasDecision('Acme/Evil'));
        self::assertFalse($mgr->hasDecision('ACME/EVIL'));
    }

    public function testExactMatchOverridesEarlierGlob(): void
    {
        // SECURITY: an explicit per-package decision must override a broader glob,
        // even if the glob was added first.
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => [
                'vendor/*' => true,
                'vendor/foo' => false, // explicit deny added later
            ]]],
        ]));
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertTrue($mgr->hasDecision('vendor/foo'));
        self::assertFalse($mgr->isAllowed('vendor/foo'), 'Explicit deny must beat earlier glob allow');
        // Other packages still match the glob
        self::assertTrue($mgr->isAllowed('vendor/bar'));
    }

    public function testExactAllowOverridesEarlierGlobDeny(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => [
                'untrusted/*' => false,
                'untrusted/specific-ok' => true,
            ]]],
        ]));
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertTrue($mgr->isAllowed('untrusted/specific-ok'));
        self::assertFalse($mgr->isAllowed('untrusted/other'));
    }

    public function testGlobMatchAllowed(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['trusted-org/*' => true]]],
        ]));
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertTrue($mgr->hasDecision('trusted-org/some-skill'));
        self::assertTrue($mgr->isAllowed('trusted-org/some-skill'));
        self::assertFalse($mgr->hasDecision('other/pkg'));
    }

    public function testGlobMatchDenied(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['blocked-org/*' => false]]],
        ]));
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertTrue($mgr->hasDecision('blocked-org/some-pkg'));
        self::assertFalse($mgr->isAllowed('blocked-org/some-pkg'));
    }

    public function testNoConfigMeansNoDecision(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertFalse($mgr->hasDecision('vendor/foo'));
        self::assertFalse($mgr->isAllowed('vendor/foo'));
    }

    public function testMissingComposerJsonIsHandled(): void
    {
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);
        self::assertFalse($mgr->hasDecision('any/pkg'));
    }

    public function testMalformedJsonIsHandled(): void
    {
        file_put_contents($this->composerJsonPath, '{ not valid json');
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

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
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertTrue($mgr->hasDecision('vendor/good'));
        self::assertFalse($mgr->hasDecision('vendor/bad'));
    }

    public function testDecideRespectsExistingAllow(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => true]]],
        ]));
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/foo'));
    }

    public function testDecideRespectsExistingDeny(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => false]]],
        ]));
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Denied, $mgr->decide('vendor/foo'));
    }

    public function testInteractivePromptIncludesRecoveryHint(): void
    {
        // The per-package prompt should mention skills:trust so users have an
        // on-screen breadcrumb even if they pick `n` and want to reverse.
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $captured = '';
        $io = $this->createStub(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('ask')->willReturnCallback(function (string $q) use (&$captured): string {
            $captured = $q;
            return 'n';
        });

        $mgr = SkillTrustManager::forComposerJson($io, $this->composerJsonPath);
        $mgr->decide('vendor/foo');

        self::assertStringContainsString('change later', $captured);
        self::assertStringContainsString('composer skills:trust vendor/foo', $captured);
        self::assertStringContainsString('[y,n,a,d,?]', $captured);
    }

    public function testInteractivePromptHelpLoop(): void
    {
        // Picking '?' must show details and re-prompt (matching Composer's
        // PluginManager pattern), not be parsed as a deny.
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $answers = ['?', 'y'];
        $io = $this->createStub(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('ask')->willReturnCallback(function () use (&$answers): string {
            return array_shift($answers);
        });

        $mgr = SkillTrustManager::forComposerJson($io, $this->composerJsonPath);
        $state = $mgr->decide('vendor/foo');

        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $state);
        // Both answers consumed
        self::assertSame([], $answers);
    }

    public function testDecideDiscardStaysPendingNotDenied(): void
    {
        // 'd' Discard should NOT persist any decision and should NOT register a
        // session denial. Re-running decide() during the same session prompts again.
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $mgr = SkillTrustManager::forComposerJson($this->interactiveIo('d'), $this->composerJsonPath);

        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Pending, $mgr->decide('vendor/foo'));
        // No persisted decision and no session entry — hasDecision is false.
        self::assertFalse($mgr->hasDecision('vendor/foo'));
    }

    public function testDecideSessionAllowReturnsAllowed(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $mgr = SkillTrustManager::forComposerJson($this->interactiveIo('a'), $this->composerJsonPath);

        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/foo'));
    }

    public function testDecideNonInteractiveSkipsAndWarns(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $io = new BufferIO();
        $mgr = SkillTrustManager::forComposerJson($io, $this->composerJsonPath);

        self::assertNotSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/new'));

        $output = $io->getOutput();
        self::assertStringContainsString('vendor/new', $output);
        self::assertStringContainsString('composer skills:trust', $output);
    }

    /**
     * Build a FirstRunInput from a list of [packageName, isDirect] pairs.
     *
     * @param list<array{0: string, 1: bool}> $packages
     */
    private function firstRunInput(array $packages): FirstRunInput
    {
        $provider = new class ($packages) implements PackageProvider {
            /** @param list<array{0: string, 1: bool}> $entries */
            public function __construct(private array $entries)
            {
            }

            public function iterAllPackages(): iterable
            {
                foreach ($this->entries as [$name, $_]) {
                    yield new PackageInfo($name, '/tmp', '1.0.0', 'ai-agent-skill', []);
                }
            }
        };
        $directs = array_values(array_map(
            static fn (array $p): string => $p[0],
            array_filter($packages, static fn (array $p): bool => $p[1]),
        ));
        return FirstRunInput::buildForFirstRun($provider, $directs);
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
        $mgr = SkillTrustManager::forComposerJson($this->interactiveIo('y'), $this->composerJsonPath);

        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/foo'));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame(['vendor/foo' => true], $data['extra']['ai-agent-skill']['allow-skills']);
    }

    public function testDecideInteractiveDenyPersistsAsFalse(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $mgr = SkillTrustManager::forComposerJson($this->interactiveIo('n'), $this->composerJsonPath);

        self::assertNotSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/bar'));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame(['vendor/bar' => false], $data['extra']['ai-agent-skill']['allow-skills']);
    }

    public function testDecideInteractiveSessionAllowDoesNotWriteFile(): void
    {
        $original = "{\n}\n";
        file_put_contents($this->composerJsonPath, $original);
        $mgr = SkillTrustManager::forComposerJson($this->interactiveIo('a'), $this->composerJsonPath);

        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/baz'));
        self::assertSame($original, file_get_contents($this->composerJsonPath));
        self::assertTrue($mgr->isAllowed('vendor/baz')); // session cache
    }

    public function testDecideInteractiveDiscardDoesNotWriteOrAllow(): void
    {
        $original = "{\n}\n";
        file_put_contents($this->composerJsonPath, $original);
        $mgr = SkillTrustManager::forComposerJson($this->interactiveIo('d'), $this->composerJsonPath);

        self::assertNotSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/qux'));
        self::assertSame($original, file_get_contents($this->composerJsonPath));
        self::assertFalse($mgr->isAllowed('vendor/qux'));
    }

    public function testDecidePersistAddsToExistingMap(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/old' => true]]],
        ], JSON_PRETTY_PRINT));
        $mgr = SkillTrustManager::forComposerJson($this->interactiveIo('y'), $this->composerJsonPath);

        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/new'));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame(
            ['vendor/old' => true, 'vendor/new' => true],
            $data['extra']['ai-agent-skill']['allow-skills'],
        );
    }

    public function testFirstRunSkippedWhenMapPresent(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => true]]],
        ], JSON_PRETTY_PRINT));
        $original = (string) file_get_contents($this->composerJsonPath);

        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);
        $mgr->applyFirstRunPolicy($this->firstRunInput([['vendor/should-not-appear', true]]));

        self::assertSame($original, file_get_contents($this->composerJsonPath));
    }

    public function testFirstRunNonInteractiveDefaultsToNoneWithWarning(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $io = new BufferIO();
        $mgr = SkillTrustManager::forComposerJson($io, $this->composerJsonPath);

        $mgr->applyFirstRunPolicy($this->firstRunInput([
            ['vendor/legacy-a', true],
            ['vendor/legacy-b', false],
        ]));

        // Map written as empty so we don't ask again.
        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame([], $data['extra']['ai-agent-skill']['allow-skills']);

        // Warning lists affected packages with skills:trust hints.
        $output = $io->getOutput();
        self::assertStringContainsString('vendor/legacy-a', $output);
        self::assertStringContainsString('vendor/legacy-b', $output);
        self::assertStringContainsString('composer skills:trust', $output);
    }

    public function testFirstRunInteractivePromptDefaultIsNone(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $io = $this->createStub(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('ask')->willReturn('n');

        $mgr = SkillTrustManager::forComposerJson($io, $this->composerJsonPath);
        $mgr->applyFirstRunPolicy($this->firstRunInput([['vendor/legacy-a', true]]));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertSame([], $data['extra']['ai-agent-skill']['allow-skills']);
    }

    public function testFirstRunInteractivePromptDirectOnly(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $io = $this->createStub(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('ask')->willReturn('d');

        $mgr = SkillTrustManager::forComposerJson($io, $this->composerJsonPath);
        $mgr->applyFirstRunPolicy($this->firstRunInput([
            ['vendor/direct', true],
            ['vendor/transitive', false],
        ]));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertSame(
            ['vendor/direct' => true],
            $data['extra']['ai-agent-skill']['allow-skills'],
        );
    }

    public function testFirstRunInteractivePromptAll(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $io = $this->createStub(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('ask')->willReturn('a');

        $mgr = SkillTrustManager::forComposerJson($io, $this->composerJsonPath);
        $mgr->applyFirstRunPolicy($this->firstRunInput([
            ['vendor/direct', true],
            ['vendor/transitive', false],
        ]));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertSame(
            ['vendor/direct' => true, 'vendor/transitive' => true],
            $data['extra']['ai-agent-skill']['allow-skills'],
        );
    }

    public function testRootPackageIsAlwaysAllowed(): void
    {
        // The user IS the root — never prompt for self-authorization.
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath, 'my-org/my-project');

        self::assertTrue($mgr->hasDecision('my-org/my-project'));
        self::assertTrue($mgr->isAllowed('my-org/my-project'));
        // decide() returns true without writing anything to composer.json (file is missing).
        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('my-org/my-project'));
        self::assertFileDoesNotExist($this->composerJsonPath);
    }

    public function testRootPackageNullByDefault(): void
    {
        // Backward compat: existing constructor signature without root name is unchanged.
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);
        self::assertFalse($mgr->hasDecision('any/pkg'));
    }

    public function testFirstRunMalformedAnswerDefaultsToNone(): void
    {
        // A bogus answer like 'yes' (instead of y/n/a/d) must default to None,
        // not be parsed as something more permissive. Pinning this so future
        // 'helpful' parsing doesn't accidentally widen trust.
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $io = $this->createStub(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('ask')->willReturn('yes');

        $mgr = SkillTrustManager::forComposerJson($io, $this->composerJsonPath);
        $mgr->applyFirstRunPolicy($this->firstRunInput([['vendor/foo', true]]));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertSame([], $data['extra']['ai-agent-skill']['allow-skills']);
    }

    public function testRevokeUnknownPackageReturnsFalse(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/known' => true]]],
        ]));
        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);

        self::assertFalse($mgr->revoke('vendor/unknown'));
        // Existing entry untouched
        self::assertTrue($mgr->isAllowed('vendor/known'));
    }

    public function testFirstRunWithNoLegacyPackagesDoesNothing(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $original = (string) file_get_contents($this->composerJsonPath);

        $mgr = SkillTrustManager::forComposerJson(new BufferIO(), $this->composerJsonPath);
        $mgr->applyFirstRunPolicy($this->firstRunInput([]));

        self::assertSame($original, file_get_contents($this->composerJsonPath));
    }

    public function testPersistPreservesExistingStringSkillsValue(): void
    {
        // Root project is itself a skill provider with extra.ai-agent-skill = "SKILL.md".
        // Persisting allow-skills must NOT destroy that — migrate it under skills sub-key.
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => 'SKILL.md'],
        ], JSON_PRETTY_PRINT));

        $mgr = SkillTrustManager::forComposerJson($this->interactiveIo('y'), $this->composerJsonPath);
        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/foo'));

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

        $mgr = SkillTrustManager::forComposerJson($this->interactiveIo('y'), $this->composerJsonPath);
        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/foo'));

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

        $mgr = SkillTrustManager::forComposerJson($io, $this->composerJsonPath);
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

        $mgr = SkillTrustManager::forComposerJson($this->interactiveIo('y'), $this->composerJsonPath);
        self::assertSame(\Netresearch\ComposerAgentSkillPlugin\Trust\TrustState::Allowed, $mgr->decide('vendor/foo'));

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertIsArray($data);
        self::assertSame(['skills/a.md'], $data['extra']['ai-agent-skill']['skills']);
        self::assertSame('value', $data['extra']['ai-agent-skill']['something-else']);
        self::assertSame(['vendor/foo' => true], $data['extra']['ai-agent-skill']['allow-skills']);
    }
}
