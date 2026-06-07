<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Commands;

/**
 * Resolves the composer.json path and root package name for a command.
 *
 * Centralizes the (Composer instance ?? cwd-based fallback) lookup that all
 * four plugin commands need. Tests that don't have a Composer instance fall
 * through to the cwd path; production paths use Composer's ConfigSource so
 * they honor `--working-dir`.
 *
 * Mixed into commands that extend {@see \Composer\Command\BaseCommand}.
 * `tryComposer()` was added in Composer 2.3; on 2.2 LTS we fall back to
 * the legacy `getComposer(false)` (still present, deprecated since 2.3).
 *
 * @phpstan-require-extends \Composer\Command\BaseCommand
 */
trait CommandContextTrait
{
    /**
     * Resolve the composer.json path and root package name.
     *
     * @return array{0: string, 1: ?string} [composerJsonPath, rootPackageName]
     */
    private function resolveContext(): array
    {
        // tryComposer() was introduced in Composer 2.3; 2.2 LTS only has
        // the deprecated getComposer(). Resolve in a way that works on both.
        // (The always-true narrowing PHPStan reports here under Composer >=2.3
        // is suppressed via phpstan.neon — see the ignoreErrors note there.)
        if (method_exists($this, 'tryComposer')) {
            $composer = $this->tryComposer();
        } else {
            /** @phpstan-ignore-next-line method.deprecated */
            $composer = $this->getComposer(false);
        }
        if ($composer !== null) {
            return [
                $composer->getConfig()->getConfigSource()->getName(),
                $composer->getPackage()->getName(),
            ];
        }
        $cwd = getcwd();
        if ($cwd === false) {
            $cwd = sys_get_temp_dir();
        }
        return [$cwd . DIRECTORY_SEPARATOR . 'composer.json', null];
    }
}
