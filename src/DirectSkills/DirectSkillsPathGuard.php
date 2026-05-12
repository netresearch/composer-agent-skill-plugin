<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\DirectSkills;

use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;

/**
 * Validates relative paths from composer.json / lock so materialization cannot escape the project root.
 */
final class DirectSkillsPathGuard
{
    /**
     * Composer-configured directory (install-dir, sources-dir, cache-dir): relative POSIX, no traversal.
     *
     * @throws DirectSkillsException
     */
    public static function assertConfigRelativeDir(string $label, string $posixPath): void
    {
        $posixPath = self::normalizeRelativePosix($posixPath);
        $posixPath = trim($posixPath, '/');
        if ($posixPath === '') {
            throw new DirectSkillsException(sprintf('%s must be a non-empty relative path.', $label));
        }
        self::assertNoTraversalOrAbsolute($label, $posixPath);
    }

    /**
     * Git materialization uses the lock commit as a single cache path segment; reject traversal or odd values.
     *
     * @return string Normalized 40-char lowercase SHA for directory names.
     *
     * @throws DirectSkillsException
     */
    public static function assertLockGitCommitSha(string $commit): string
    {
        $c = strtolower(trim($commit));
        if (!preg_match('/^[0-9a-f]{40}$/', $c)) {
            throw new DirectSkillsException(
                'composer.skills.lock git commit must be a 40-character hexadecimal SHA (possible lock tampering).',
            );
        }

        return $c;
    }

    /**
     * Lockfile path fields (install-path, path, url for path-type packages).
     *
     * @throws DirectSkillsException
     */
    public static function assertLockRelativePosix(string $field, string $posixPath, bool $allowDotOnly = false): void
    {
        $norm = self::normalizeRelativePosix($posixPath);
        if ($allowDotOnly && $norm === '.') {
            return;
        }
        if ($norm === '' || str_contains($norm, "\0")) {
            throw new DirectSkillsException(sprintf('Invalid %s in lockfile.', $field));
        }
        self::assertNoTraversalOrAbsolute($field, $norm);
    }

    /**
     * Ensures a filesystem path resolves under $projectRoot (after realpath).
     *
     * @throws DirectSkillsException
     */
    public static function assertResolvedUnderProject(string $projectRoot, string $absolutePath): void
    {
        $rootReal = realpath($projectRoot);
        if ($rootReal === false) {
            throw new DirectSkillsException('Cannot resolve project root.');
        }
        $check = $absolutePath;
        while (!is_dir($check) && $check !== dirname($check)) {
            $check = dirname($check);
        }
        $dirReal = realpath($check);
        if ($dirReal === false) {
            throw new DirectSkillsException('Cannot resolve materialized path under project.');
        }
        if ($dirReal !== $rootReal && !str_starts_with($dirReal, $rootReal . DIRECTORY_SEPARATOR)) {
            throw new DirectSkillsException('Materialized path escapes project root (possible lock tampering).');
        }
    }

    /**
     * @throws DirectSkillsException
     */
    private static function normalizeRelativePosix(string $posixPath): string
    {
        $norm = str_replace('\\', '/', $posixPath);
        while (str_starts_with($norm, './')) {
            $norm = substr($norm, 2);
        }

        return $norm;
    }

    private static function assertNoTraversalOrAbsolute(string $label, string $norm): void
    {
        if (str_starts_with($norm, '/')) {
            throw new DirectSkillsException(sprintf('%s must not be an absolute path.', $label));
        }
        if (preg_match('#^[A-Za-z]:[/\\\\]#', $norm) === 1) {
            throw new DirectSkillsException(sprintf('%s must not be a Windows absolute path.', $label));
        }
        foreach (explode('/', $norm) as $seg) {
            if ($seg === '' || $seg === '..') {
                throw new DirectSkillsException(sprintf('%s must not contain empty segments or "..".', $label));
            }
            if ($seg === '.') {
                throw new DirectSkillsException(sprintf('%s must not contain "." segments (use plain names).', $label));
            }
        }
    }
}
