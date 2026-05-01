<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Trust;

use Composer\IO\BufferIO;
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
}
