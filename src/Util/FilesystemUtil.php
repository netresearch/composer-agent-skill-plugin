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
     * Express $absoluteOrRelative as a POSIX path relative to $projectRoot when it resolves under
     * that root; otherwise returns a slash-normalized copy of the input path string.
     *
     * Uses a strict directory-prefix check so siblings like "/proj-other" are not treated as
     * under "/proj".
     */
    public static function relativePosixFromProjectRoot(string $projectRoot, string $absoluteOrRelative): string
    {
        $root = realpath($projectRoot);
        if ($root === false) {
            return str_replace(DIRECTORY_SEPARATOR, '/', $absoluteOrRelative);
        }
        $path = realpath($absoluteOrRelative);
        if ($path === false) {
            return str_replace(DIRECTORY_SEPARATOR, '/', $absoluteOrRelative);
        }
        if ($path === $root) {
            return '.';
        }
        $prefix = $root . DIRECTORY_SEPARATOR;
        if (!str_starts_with($path, $prefix)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', $absoluteOrRelative);
        }
        $rel = substr($path, strlen($prefix));
        $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);

        return './' . $rel;
    }

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
                // recursive cleanup of plugin-managed directory contents; path from directory iteration, not user input
            } elseif (file_exists($path) && !unlink($path)) { // nosemgrep: php.lang.security.unlink-use.unlink-use
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
