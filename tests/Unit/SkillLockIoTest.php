<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\DirectSkills\PluginVersion;
use Netresearch\ComposerAgentSkillPlugin\Lock\LockedSkillPackage;
use Netresearch\ComposerAgentSkillPlugin\Lock\SkillLockFile;
use Netresearch\ComposerAgentSkillPlugin\Lock\SkillLockIo;
use PHPUnit\Framework\TestCase;

final class SkillLockIoTest extends TestCase
{
    public function testRoundTripWriteRead(): void
    {
        $root = sys_get_temp_dir() . '/skill-lock-io-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        try {
            $pkg = new LockedSkillPackage(
                'demo-skill',
                'local/demo',
                'path',
                './skills/demo',
                null,
                'local',
                '.',
                'sha256:abcd',
                'vendor/agent-skills/installed/demo-skill',
                ['description' => 'x'],
            );
            $lock = new SkillLockFile(1, PluginVersion::detect(), 'hashval', '2026-01-01T00:00:00+00:00', [$pkg]);
            $io = new SkillLockIo($root);
            $io->write($lock);

            self::assertFileExists($root . '/composer.skills.lock');
            $read = $io->read();
            self::assertNotNull($read);
            self::assertSame('hashval', $read->contentHash);
            self::assertCount(1, $read->packages);
            self::assertSame('demo-skill', $read->packages[0]->name);
            self::assertSame('sha256:abcd', $read->packages[0]->checksum);
        } finally {
            @unlink($root . '/composer.skills.lock');
            @rmdir($root);
        }
    }
}
