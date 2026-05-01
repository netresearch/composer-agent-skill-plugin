<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Trust;

use Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider;

/**
 * Inputs to {@see SkillTrustManager::applyFirstRunPolicy()}.
 *
 * Wraps the legacy-package list and direct-dependency lookup so the trust
 * manager doesn't have to know about {@see PackageProvider} or root composer.json
 * structure — and so the invariant `direct ⊆ legacy` is enforced at construction
 * rather than in caller-supplied lists that could drift.
 *
 * Use {@see self::buildForFirstRun()} from production paths; the primary
 * constructor is for tests that need to pre-compute fixtures.
 */
final readonly class FirstRunInput
{
    /**
     * @param list<string>         $legacyPackages   Installed packages with type:ai-agent-skill
     * @param array<string, true>  $directLookup     Subset of $legacyPackages directly required by root composer.json
     */
    private function __construct(
        public array $legacyPackages,
        private array $directLookup,
    ) {
    }

    /**
     * Build by iterating the package provider once and computing the legacy
     * list and the direct-dependency intersection in a single pass.
     *
     * @param list<string> $rootRequireNames Names from getRequires() + getDevRequires() of the root package
     */
    public static function buildForFirstRun(PackageProvider $packages, array $rootRequireNames): self
    {
        $directSet = array_fill_keys($rootRequireNames, true);
        $legacy = [];
        $directLookup = [];
        foreach ($packages->iterAllPackages() as $pkg) {
            if ($pkg->type !== 'ai-agent-skill') {
                continue;
            }
            $legacy[] = $pkg->name;
            if (isset($directSet[$pkg->name])) {
                $directLookup[$pkg->name] = true;
            }
        }
        return new self($legacy, $directLookup);
    }

    public function isEmpty(): bool
    {
        return $this->legacyPackages === [];
    }

    public function isDirect(string $packageName): bool
    {
        return isset($this->directLookup[$packageName]);
    }

    /**
     * @return list<string>
     */
    public function directOnly(): array
    {
        return array_values(array_filter(
            $this->legacyPackages,
            fn (string $p): bool => $this->isDirect($p),
        ));
    }
}
