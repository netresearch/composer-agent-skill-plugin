<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Installer;

use Composer\IO\IOInterface;
use Netresearch\ComposerAgentSkillPlugin\Util\FilesystemUtil;

/**
 * Recursive copy of a skill directory (files only; follows symlinks as files — copy content).
 */
final class SkillDirectoryCopier
{
    public function copyInto(string $sourceDir, string $targetDir, ?IOInterface $io = null): void
    {
        $sourceDir = realpath($sourceDir);
        if ($sourceDir === false || !is_dir($sourceDir)) {
            throw new \InvalidArgumentException('Invalid source directory.');
        }
        if (is_dir($targetDir)) {
            FilesystemUtil::removeDirectoryTree($targetDir, $io);
        }
        if (!mkdir($targetDir, FilesystemUtil::DIRECTORY_MODE, true) && !is_dir($targetDir)) {
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
                if (!is_dir($dest) && !mkdir($dest, FilesystemUtil::DIRECTORY_MODE, true) && !is_dir($dest)) {
                    throw new \RuntimeException(sprintf('mkdir failed %s', $dest));
                }
            } else {
                $destDir = dirname($dest);
                if (!is_dir($destDir) && !mkdir($destDir, FilesystemUtil::DIRECTORY_MODE, true) && !is_dir($destDir)) {
                    throw new \RuntimeException(sprintf('mkdir failed %s', $destDir));
                }
                if (!copy($fileInfo->getPathname(), $dest)) {
                    throw new \RuntimeException(sprintf('copy failed %s', $dest));
                }
            }
        }
    }
}
