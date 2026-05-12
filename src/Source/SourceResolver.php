<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Source;

/**
 * Parses shorthand and URLs into a ResolvedSource (MVP).
 */
final class SourceResolver
{
    /**
     * @throws \InvalidArgumentException
     */
    public function resolve(string $input, ?string $nameOverride = null, ?string $refCli = null): ResolvedSource
    {
        $input = trim($input);
        if ($input === '') {
            throw new \InvalidArgumentException('Empty source.');
        }
        $input = $this->expandUserHome($input);

        // Local path
        if ($this->looksLikePath($input)) {
            $real = realpath($input);
            if ($real === false || !is_dir($real)) {
                throw new \InvalidArgumentException(sprintf('Local path does not exist or is not a directory: %s', $input));
            }
            $base = basename($real);
            $name = $nameOverride ?? 'local/' . $base;

            return new ResolvedSource($name, 'path', null, null, $real);
        }

        // GitHub tree URL
        if (preg_match('#^https?://github\.com/([^/]+)/([^/]+)/tree/([^/]+)/(.+)$#', $input, $m)) {
            $owner = $m[1];
            $repo = preg_replace('/\.git$/', '', $m[2]);
            $ref = $m[3];
            $sub = $m[4];
            $url = sprintf('https://github.com/%s/%s.git', $owner, $repo);
            $name = $nameOverride ?? $owner . '/' . $repo;

            return new ResolvedSource($name, 'github', $url, $refCli ?? $ref, $sub);
        }

        // HTTPS github repo (repo segment may contain dots, e.g. my.skill; optional .git suffix)
        if (preg_match('~^https?://github\.com/([^/]+)/([^/?#]+?)(?:\.git)?/?$~', $input, $m)) {
            $owner = $m[1];
            $repo = $m[2];
            $url = sprintf('https://github.com/%s/%s.git', $owner, $repo);
            $name = $nameOverride ?? $owner . '/' . $repo;

            return new ResolvedSource($name, 'github', $url, $refCli, null);
        }

        // git@github.com:owner/repo.git
        if (preg_match('#^git@github\.com:([^/]+)/([^/]+?)(\.git)?$#', $input, $m)) {
            $owner = $m[1];
            $repo = preg_replace('/\.git$/', '', $m[2]);
            $url = sprintf('https://github.com/%s/%s.git', $owner, $repo);
            $name = $nameOverride ?? $owner . '/' . $repo;

            return new ResolvedSource($name, 'github', $url, $refCli, null);
        }

        // owner/repo:ref — ref may be a branch/tag or a semver constraint (^1.2, ~2, >=1,<2, …)
        if (preg_match('#^([a-zA-Z0-9_.-]+)/([a-zA-Z0-9_.-]+):(.+)$#', $input, $m)) {
            $owner = $m[1];
            $repo = preg_replace('/\.git$/', '', $m[2]);
            $suffix = trim($m[3]);
            $url = sprintf('https://github.com/%s/%s.git', $owner, $repo);
            $name = $nameOverride ?? $owner . '/' . $repo;
            $ref = $refCli ?? $suffix;

            return new ResolvedSource($name, 'github', $url, $ref, null);
        }

        // owner/repo shorthand
        if (preg_match('#^([a-zA-Z0-9_.-]+)/([a-zA-Z0-9_.-]+)$#', $input, $m)) {
            $owner = $m[1];
            $repo = preg_replace('/\.git$/', '', $m[2]);
            $url = sprintf('https://github.com/%s/%s.git', $owner, $repo);
            $name = $nameOverride ?? $owner . '/' . $repo;

            return new ResolvedSource($name, 'github', $url, $refCli, null);
        }

        throw new \InvalidArgumentException(sprintf('Unsupported source format: %s', $input));
    }

    private function looksLikePath(string $input): bool
    {
        return str_starts_with($input, '.')
            || str_starts_with($input, '/')
            || str_starts_with($input, '~')
            || (strlen($input) > 1 && $input[1] === ':'); // Windows drive
    }

    private function expandUserHome(string $input): string
    {
        if (!str_starts_with($input, '~')) {
            return $input;
        }
        $home = getenv('HOME');
        if (!is_string($home) || $home === '') {
            $home = getenv('USERPROFILE');
        }
        if (!is_string($home) || $home === '') {
            return $input;
        }
        if ($input === '~') {
            return $home;
        }
        if (str_starts_with($input, '~/') || str_starts_with($input, '~\\')) {
            return $home . substr($input, 1);
        }

        return $input;
    }
}
