<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Util;

use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\GitRemoteHeadLookup;

/**
 * Maps a stored ref (semver constraint, branch, tag, or commit-ish) to the remote commit SHA that would be checked out.
 */
final class GitRefCommitResolver implements GitRemoteHeadLookup
{
    public function resolveRemoteCommit(string $url, string $storedRef): string
    {
        $storedRef = trim($storedRef);
        if ($storedRef === '') {
            throw new DirectSkillsException('Empty git ref for remote resolution.');
        }
        if (self::looksLikeCommitSha($storedRef)) {
            return strtolower($storedRef);
        }
        if (GitSemverResolver::looksLikeSemverConstraint($storedRef)) {
            $tag = GitSemverResolver::resolveToGitRef($url, $storedRef);

            return $this->tagTipCommit($url, $tag);
        }

        try {
            $line = GitCli::mustRun(['ls-remote', $url, 'refs/heads/' . $storedRef]);
            $hash = self::firstHashFromLsRemoteOutput($line);
            if ($hash !== null) {
                return $hash;
            }
        } catch (\RuntimeException) {
            // try as tag below
        }

        return $this->tagTipCommit($url, $storedRef);
    }

    public static function commitsEquivalent(string $a, string $b): bool
    {
        $a = strtolower(trim($a));
        $b = strtolower(trim($b));
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }

    private function tagTipCommit(string $url, string $tag): string
    {
        try {
            $out = GitCli::mustRun(['ls-remote', $url, 'refs/tags/' . $tag . '^{}', 'refs/tags/' . $tag]);
        } catch (\RuntimeException $e) {
            throw new DirectSkillsException(sprintf('Could not resolve tag %s on remote: %s', $tag, $e->getMessage()), 0, $e);
        }
        $peeled = null;
        $direct = null;
        foreach (explode("\n", trim($out)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('#^([0-9a-f]{40})\s+refs/tags/(.+)$#i', $line, $m)) {
                continue;
            }
            if (str_ends_with($m[2], '^{}')) {
                $peeled = strtolower($m[1]);
            } elseif ($m[2] === $tag) {
                $direct = strtolower($m[1]);
            }
        }
        $resolved = $peeled ?? $direct;
        if ($resolved === null) {
            throw new DirectSkillsException(sprintf('No remote tag or ambiguous ref for %s on %s.', $tag, $url));
        }

        return $resolved;
    }

    private static function firstHashFromLsRemoteOutput(string $output): ?string
    {
        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('#^([0-9a-f]{40})\s+#i', $line, $m)) {
                return strtolower($m[1]);
            }
        }

        return null;
    }

    private static function looksLikeCommitSha(string $ref): bool
    {
        return (bool) preg_match('/^[0-9a-f]{7,40}$/i', $ref);
    }
}
