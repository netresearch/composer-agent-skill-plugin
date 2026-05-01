<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Package;

use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use PHPUnit\Framework\TestCase;

final class PackageInfoTest extends TestCase
{
    public function testHoldsAllFields(): void
    {
        $info = new PackageInfo(
            name: 'vendor/foo',
            installPath: '/tmp/vendor/foo',
            version: '1.2.3',
            type: 'library',
            extra: ['ai-agent-skill' => 'SKILL.md'],
        );

        self::assertSame('vendor/foo', $info->name);
        self::assertSame('/tmp/vendor/foo', $info->installPath);
        self::assertSame('1.2.3', $info->version);
        self::assertSame('library', $info->type);
        self::assertSame(['ai-agent-skill' => 'SKILL.md'], $info->extra);
    }

    public function testDeclaresSkillsWhenExtraKeyPresent(): void
    {
        $info = new PackageInfo('a/b', '/p', '1.0.0', 'library', ['ai-agent-skill' => 'SKILL.md']);
        self::assertTrue($info->declaresSkills());
    }

    public function testDeclaresSkillsForLegacyType(): void
    {
        $info = new PackageInfo('a/b', '/p', '1.0.0', 'ai-agent-skill', []);
        self::assertTrue($info->declaresSkills());
    }

    public function testDoesNotDeclareSkillsOtherwise(): void
    {
        $info = new PackageInfo('a/b', '/p', '1.0.0', 'library', []);
        self::assertFalse($info->declaresSkills());
    }
}
