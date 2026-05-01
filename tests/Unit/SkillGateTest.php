<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Netresearch\ComposerAgentSkillPlugin\SkillGate;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use Netresearch\ComposerAgentSkillPlugin\Trust\TrustState;
use PHPUnit\Framework\TestCase;

final class SkillGateTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/skill-gate-' . uniqid();
        mkdir($this->rootDir);
    }

    protected function tearDown(): void
    {
        $cj = $this->rootDir . '/composer.json';
        if (file_exists($cj)) {
            unlink($cj);
        }
        if (is_dir($this->rootDir)) {
            rmdir($this->rootDir);
        }
    }

    /**
     * @param array<string, bool> $allowMap
     */
    private function trustWith(array $allowMap, ?IOInterface $io = null): SkillTrustManager
    {
        file_put_contents(
            $this->rootDir . '/composer.json',
            (string) json_encode(['extra' => ['ai-agent-skill' => ['allow-skills' => $allowMap]]])
        );
        return new SkillTrustManager($io ?? new BufferIO(), $this->rootDir);
    }

    /** @return array{name: string, description: string, location: string, package: string, version: string, file: string, trust_state: TrustState} */
    private function skill(string $package, TrustState $state): array
    {
        return [
            'name' => $package . '-skill',
            'description' => 'd',
            'location' => '/x',
            'package' => $package,
            'version' => '1.0.0',
            'file' => 'SKILL.md',
            'trust_state' => $state,
        ];
    }

    public function testAllowedSkillsPassThrough(): void
    {
        $trust = $this->trustWith(['ok/pkg' => true]);
        $gate = new SkillGate($trust);
        $skills = [$this->skill('ok/pkg', TrustState::Allowed)];

        $result = $gate->gate($skills);

        self::assertCount(1, $result->allowed);
        self::assertSame([], $result->denied);
        self::assertSame([], $result->pending);
    }

    public function testDeniedSkillsAreFilteredAndReported(): void
    {
        $trust = $this->trustWith(['no/pkg' => false]);
        $gate = new SkillGate($trust);
        $skills = [$this->skill('no/pkg', TrustState::Denied)];

        $result = $gate->gate($skills);

        self::assertSame([], $result->allowed);
        self::assertSame(['no/pkg'], $result->denied);
        self::assertSame([], $result->pending);
    }

    public function testPendingNonInteractiveBecomesPending(): void
    {
        $trust = $this->trustWith([], new BufferIO()); // non-interactive default
        $gate = new SkillGate($trust);
        $skills = [$this->skill('mystery/pkg', TrustState::Pending)];

        $result = $gate->gate($skills);

        self::assertSame([], $result->allowed);
        self::assertSame([], $result->denied);
        self::assertSame(['mystery/pkg'], $result->pending);
    }

    public function testPendingThatGetsApprovedFlowsToAllowed(): void
    {
        $io = $this->createStub(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('ask')->willReturn('y'); // allow & persist

        $trust = $this->trustWith([], $io);
        $gate = new SkillGate($trust);
        $skills = [$this->skill('mystery/pkg', TrustState::Pending)];

        $result = $gate->gate($skills);

        self::assertCount(1, $result->allowed);
        self::assertSame([], $result->denied);
        self::assertSame([], $result->pending);
    }

    public function testPendingThatGetsDeniedFlowsToDenied(): void
    {
        $io = $this->createStub(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('ask')->willReturn('n'); // deny & persist

        $trust = $this->trustWith([], $io);
        $gate = new SkillGate($trust);
        $skills = [$this->skill('mystery/pkg', TrustState::Pending)];

        $result = $gate->gate($skills);

        self::assertSame([], $result->allowed);
        self::assertSame(['mystery/pkg'], $result->denied);
        self::assertSame([], $result->pending);
    }

    public function testPackagesAppearOnlyOnceInDeniedOrPending(): void
    {
        // A package with multiple skills should appear once in the bucket lists
        $trust = $this->trustWith(['multi/pkg' => false]);
        $gate = new SkillGate($trust);
        $skills = [
            $this->skill('multi/pkg', TrustState::Denied),
            array_merge($this->skill('multi/pkg', TrustState::Denied), ['name' => 'multi/pkg-skill-2']),
        ];

        $result = $gate->gate($skills);

        self::assertSame(['multi/pkg'], $result->denied);
    }
}
