<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\Util\GitCli;
use PHPUnit\Framework\TestCase;

/**
 * Verifies git CLI failures surface as exceptions (no silent success).
 */
final class GitCliTest extends TestCase
{
    public function testMustRunThrowsWhenGitFails(): void
    {
        $tmp = sys_get_temp_dir() . '/gitcli-test-' . bin2hex(random_bytes(6));
        mkdir($tmp, 0777, true);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('git');
            GitCli::mustRun(['clone', '/this/path/does/not/exist-' . bin2hex(random_bytes(4)), $tmp . '/out']);
        } finally {
            $this->rmTree($tmp);
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
        foreach ($it as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }
            $p = $fileInfo->getPathname();
            $fileInfo->isDir() ? @rmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
