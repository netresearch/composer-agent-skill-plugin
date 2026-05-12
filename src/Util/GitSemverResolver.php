<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Util;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;

/**
 * Resolves Composer-style semver constraints against git tags from {@see GitCli::lsRemoteTags}.
 */
final class GitSemverResolver
{
    /**
     * True when $ref should be resolved against remote tags (not passed through as a literal git ref).
     */
    public static function looksLikeSemverConstraint(string $ref): bool
    {
        $t = trim($ref);

        return $t !== '' && (bool) preg_match('/[\^~*]|>=|<=|>|<|!=|\|\||,/', $t);
    }

    /**
     * If {@see looksLikeSemverConstraint} is true, lists remote tags and returns the best-matching tag
     * name for shallow clone; otherwise returns $ref unchanged.
     *
     * @throws DirectSkillsException
     */
    public static function resolveToGitRef(string $url, string $ref): string
    {
        if (!self::looksLikeSemverConstraint($ref)) {
            return $ref;
        }
        $normToTag = self::normalizedVersionsToTags(GitCli::lsRemoteTags($url));

        return self::pickTagForConstraint($url, $ref, $normToTag);
    }

    /**
     * @param array<string, string> $normToTag VersionParser-normalized version => tag name
     *
     * @throws DirectSkillsException
     */
    public static function pickTagForConstraint(string $url, string $constraint, array $normToTag): string
    {
        if ($normToTag === []) {
            throw new DirectSkillsException(sprintf('No semver tags found for %s to satisfy %s.', $url, $constraint));
        }
        $versions = array_keys($normToTag);
        $satisfied = Semver::satisfiedBy($versions, $constraint);
        if ($satisfied === []) {
            throw new DirectSkillsException(sprintf('No tag on %s satisfies semver constraint %s.', $url, $constraint));
        }
        $best = Semver::rsort($satisfied)[0];

        return $normToTag[$best];
    }

    /**
     * @return array<string, string> VersionParser-normalized version => annotated tag name
     */
    public static function normalizedVersionsToTags(string $lsRemoteOutput): array
    {
        $vp = new VersionParser();
        $map = [];
        foreach (explode("\n", $lsRemoteOutput) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('#^([0-9a-f]{7,40})\s+refs/tags/(.+)$#i', $line, $m)) {
                continue;
            }
            $tag = $m[2];
            if (str_ends_with($tag, '^{}')) {
                continue;
            }
            try {
                $norm = $vp->normalize($tag);
            } catch (\UnexpectedValueException) {
                continue;
            }
            $map[$norm] = $tag;
        }

        return $map;
    }
}
