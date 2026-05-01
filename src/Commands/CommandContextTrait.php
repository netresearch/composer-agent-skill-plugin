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
 * Mixed into commands that extend {@see \Composer\Command\BaseCommand}, which
 * exposes the `tryComposer()` lookup we depend on.
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
        $composer = $this->tryComposer();
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
