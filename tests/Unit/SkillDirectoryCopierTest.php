<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;
use Netresearch\ComposerAgentSkillPlugin\Installer\SkillDirectoryCopier;
use PHPUnit\Framework\TestCase;

final class SkillDirectoryCopierTest extends TestCase
{
    public function testRejectsSymlinkInSourceTree(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('symlink() not available');
        }
        $root = sys_get_temp_dir() . '/skill-copier-' . bin2hex(random_bytes(5));
        $src = $root . '/src';
        mkdir($src, 0755, true);
        file_put_contents($root . '/secret.txt', 'exfil');
        try {
            if (!@symlink($root . '/secret.txt', $src . '/SKILL.md')) {
                self::markTestSkipped('Could not create symlink (permissions / OS policy)');
            }
            $this->expectException(DirectSkillsException::class);
            $this->expectExceptionMessage('Symlinks are not allowed');
            (new SkillDirectoryCopier())->copyInto($src, $root . '/dest', null);
        } finally {
            $this->rmTree($root);
        }
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $p = $f->getPathname();
            if ($f->isLink() || $f->isFile()) {
                @unlink($p);
            } else {
                @rmdir($p);
            }
        }
        @rmdir($dir);
    }
}
