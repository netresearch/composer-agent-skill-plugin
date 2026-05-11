<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Installer;

/**
 * Stable SHA-256 over sorted relative file paths + LF-normalized contents.
 */
final class SkillDirectoryHasher
{
    public function hashSkillDirectory(string $absoluteDir): string
    {
        $absoluteDir = realpath($absoluteDir);
        if ($absoluteDir === false || !is_dir($absoluteDir)) {
            throw new \InvalidArgumentException('Invalid skill directory.');
        }

        $files = $this->collectFiles($absoluteDir);
        sort($files, SORT_STRING);

        $ctx = hash_init('sha256');
        foreach ($files as $rel) {
            $full = $absoluteDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $content = file_get_contents($full);
            if ($content === false) {
                throw new \RuntimeException(sprintf('Failed reading %s', $full));
            }
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            hash_update($ctx, $rel . "\0");
            hash_update($ctx, $content . "\0");
        }

        return 'sha256:' . hash_final($ctx);
    }

    /**
     * @return list<string> relative paths with /
     */
    private function collectFiles(string $root): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }
            $full = $fileInfo->getPathname();
            $rel = substr($full, strlen($root) + 1);
            $out[] = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
        }

        return $out;
    }
}
