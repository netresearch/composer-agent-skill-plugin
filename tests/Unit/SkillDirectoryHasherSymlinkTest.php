<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\Installer\SkillDirectoryHasher;
use PHPUnit\Framework\TestCase;

final class SkillDirectoryHasherSymlinkTest extends TestCase
{
    public function testRejectsSymlinkWhenHashing(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('symlink() not available');
        }
        $root = sys_get_temp_dir() . '/skill-hash-sym-' . bin2hex(random_bytes(5));
        mkdir($root, 0777, true);
        file_put_contents($root . '/real.md', 'x');
        try {
            if (!@symlink($root . '/real.md', $root . '/SKILL.md')) {
                self::markTestSkipped('Could not create symlink');
            }
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Symlinks are not allowed');
            (new SkillDirectoryHasher())->hashSkillDirectory($root);
        } finally {
            @unlink($root . '/SKILL.md');
            @unlink($root . '/real.md');
            @rmdir($root);
        }
    }
}
