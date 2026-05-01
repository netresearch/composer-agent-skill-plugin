<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Package;

use Netresearch\ComposerAgentSkillPlugin\Package\InstalledVersionsProvider;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider;
use PHPUnit\Framework\TestCase;

final class InstalledVersionsProviderTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(PackageProvider::class, new InstalledVersionsProvider());
    }

    public function testYieldsCurrentRootPackageAtMinimum(): void
    {
        $provider = new InstalledVersionsProvider();
        $packages = iterator_to_array($provider->iterAllPackages(), false);

        self::assertNotEmpty($packages);
        foreach ($packages as $pkg) {
            self::assertInstanceOf(PackageInfo::class, $pkg);
            self::assertNotSame('', $pkg->name);
        }
    }
}
