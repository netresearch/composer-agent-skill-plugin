<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Discovery;

use Netresearch\ComposerAgentSkillPlugin\Util\SkillMarkdownParser;

/**
 * Find directories under $root that contain SKILL.md (recursive, with ignore dirs).
 */
final class FilesystemSkillDiscovery
{
    /** @var array<int, string> */
    private const IGNORE_DIR_NAMES = [
        '.git',
        'vendor',
        'node_modules',
        '.agent-skills',
        '.cache',
        'tmp',
    ];

    /**
     * @return list<array{relativePath: string, skillDir: string, skillFile: string}>
     */
    public function discoverSkillRoots(string $rootAbsolute): array
    {
        $rootAbsolute = realpath($rootAbsolute);
        if ($rootAbsolute === false || !is_dir($rootAbsolute)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootAbsolute, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }
            if ($fileInfo->getFilename() !== 'SKILL.md') {
                continue;
            }
            $skillDir = dirname($fileInfo->getPathname());
            if ($this->pathContainsIgnoredDir($rootAbsolute, $skillDir)) {
                continue;
            }
            $relativeFromRoot = ltrim(str_replace($rootAbsolute, '', $skillDir), DIRECTORY_SEPARATOR);
            $relativeForward = str_replace(DIRECTORY_SEPARATOR, '/', $relativeFromRoot);
            $found[] = [
                'relativePath' => $relativeForward === '' ? '.' : $relativeForward,
                'skillDir' => $skillDir,
                'skillFile' => $fileInfo->getPathname(),
            ];
        }

        return $found;
    }

    /**
     * @param list<string> $skillNamesFilter skill ids or '*' for all
     * @return list<array{name: string, description: string, relativeSkillDir: string}>
     */
    public function discoverFiltered(string $rootAbsolute, array $skillNamesFilter): array
    {
        $wantAll = $skillNamesFilter === ['*'] || in_array('*', $skillNamesFilter, true);
        $roots = $this->discoverSkillRoots($rootAbsolute);
        $out = [];
        foreach ($roots as $item) {
            $parsed = SkillMarkdownParser::parseNameDescription($item['skillFile']);
            if ($parsed === null) {
                continue;
            }
            if (!$wantAll && !in_array($parsed['name'], $skillNamesFilter, true)) {
                continue;
            }
            $out[] = [
                'name' => $parsed['name'],
                'description' => $parsed['description'],
                'relativeSkillDir' => $item['relativePath'],
            ];
        }

        return $out;
    }

    private function pathContainsIgnoredDir(string $rootAbsolute, string $absoluteDir): bool
    {
        if (strlen($absoluteDir) < strlen($rootAbsolute)) {
            return false;
        }
        $rel = substr($absoluteDir, strlen($rootAbsolute));
        if ($rel === '') {
            return false;
        }
        $rel = trim($rel, DIRECTORY_SEPARATOR);
        if ($rel === '') {
            return false;
        }
        foreach (explode(DIRECTORY_SEPARATOR, $rel) as $segment) {
            if ($segment === '') {
                continue;
            }
            if (in_array($segment, self::IGNORE_DIR_NAMES, true)) {
                return true;
            }
        }

        return false;
    }
}
