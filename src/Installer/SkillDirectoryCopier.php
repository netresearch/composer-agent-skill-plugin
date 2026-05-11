<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Installer;

/**
 * Recursive copy of a skill directory (files only; follows symlinks as files — copy content).
 */
final class SkillDirectoryCopier
{
    public function copyInto(string $sourceDir, string $targetDir): void
    {
        $sourceDir = realpath($sourceDir);
        if ($sourceDir === false || !is_dir($sourceDir)) {
            throw new \InvalidArgumentException('Invalid source directory.');
        }
        if (is_dir($targetDir)) {
            $this->rmTree($targetDir);
        }
        if (!mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(sprintf('Cannot create %s', $targetDir));
        }
        $targetRoot = realpath($targetDir);
        if ($targetRoot === false) {
            throw new \RuntimeException(sprintf('Cannot resolve target %s', $targetDir));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }
            $sub = substr($fileInfo->getPathname(), strlen($sourceDir) + 1);
            $dest = $targetRoot . DIRECTORY_SEPARATOR . $sub;
            if ($fileInfo->isDir()) {
                if (!is_dir($dest) && !mkdir($dest, 0777, true) && !is_dir($dest)) {
                    throw new \RuntimeException(sprintf('mkdir failed %s', $dest));
                }
            } else {
                $destDir = dirname($dest);
                if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
                    throw new \RuntimeException(sprintf('mkdir failed %s', $destDir));
                }
                if (!copy($fileInfo->getPathname(), $dest)) {
                    throw new \RuntimeException(sprintf('copy failed %s', $dest));
                }
            }
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
            $path = $fileInfo->getPathname();
            if ($fileInfo->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
