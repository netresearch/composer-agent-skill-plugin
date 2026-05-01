<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Package;

interface PackageProvider
{
    /**
     * @return iterable<PackageInfo>
     */
    public function iterAllPackages(): iterable;
}
