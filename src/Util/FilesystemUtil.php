<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Util;

use Composer\IO\IOInterface;

/**
 * Shared filesystem helpers (directory removal, safe modes).
 */
final class FilesystemUtil
{
    public const DIRECTORY_MODE = 0755;

    /**
     * Remove a directory tree best-effort. Per-path failures are reported when $io is verbose.
     */
    public static function removeDirectoryTree(string $dir, ?IOInterface $io = null): void
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
            $path = $fileInfo->getPathname();
            if ($fileInfo->isDir()) {
                if (is_dir($path) && !rmdir($path)) {
                    self::reportFsFailure($io, 'rmdir', $path);
                }
            } elseif (file_exists($path) && !unlink($path)) {
                self::reportFsFailure($io, 'unlink', $path);
            }
        }
        if (is_dir($dir) && !rmdir($dir)) {
            self::reportFsFailure($io, 'rmdir', $dir);
        }
    }

    private static function reportFsFailure(?IOInterface $io, string $operation, string $path): void
    {
        if ($io !== null && $io->isVerbose()) {
            $io->writeError(sprintf('<warning>%s failed for %s</warning>', $operation, $path));
        }
    }
}
