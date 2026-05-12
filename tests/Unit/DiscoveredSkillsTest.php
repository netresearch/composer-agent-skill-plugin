<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\Trust\TrustState;
use Netresearch\ComposerAgentSkillPlugin\Util\DiscoveredSkills;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class DiscoveredSkillsTest extends TestCase
{
    public function testPackageVersusDirectDuplicateNameThrows(): void
    {
        $row = static fn (string $name, string $package): array => [
            'name' => $name,
            'description' => 'd',
            'location' => '/x',
            'package' => $package,
            'version' => '1',
            'file' => 'SKILL.md',
            'trust_state' => TrustState::Allowed,
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate skill name "dup" from composer package and direct install.');
        DiscoveredSkills::mergePreferringPackageOrder(
            [$row('dup', 'vendor/pkg')],
            [$row('dup', 'direct:src/s1')],
            new BufferedOutput(),
        );
    }

    public function testDuplicateWithinPackagesStillEmitsNote(): void
    {
        $row = static fn (string $pkg): array => [
            'name' => 'same',
            'description' => 'd',
            'location' => '/x',
            'package' => $pkg,
            'version' => '1',
            'file' => 'SKILL.md',
            'trust_state' => TrustState::Allowed,
        ];
        $out = new BufferedOutput();
        $merged = DiscoveredSkills::mergePreferringPackageOrder(
            [$row('a/pkg'), $row('b/pkg')],
            [],
            $out,
        );
        self::assertCount(1, $merged);
        self::assertStringContainsString('Duplicate skill name', $out->fetch());
    }
}
