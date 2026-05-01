<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Package;

use Composer\InstalledVersions;

final class InstalledVersionsProvider implements PackageProvider
{
    public function iterAllPackages(): iterable
    {
        foreach (InstalledVersions::getInstalledPackages() as $name) {
            $installPath = InstalledVersions::getInstallPath($name);
            $version = InstalledVersions::getPrettyVersion($name);

            if ($installPath === null || $version === null) {
                continue;
            }

            $composerJson = $installPath . DIRECTORY_SEPARATOR . 'composer.json';
            $type = 'library';
            /** @var array<string, mixed> $extra */
            $extra = [];

            if (file_exists($composerJson)) {
                $data = json_decode((string) file_get_contents($composerJson), true);
                if (is_array($data)) {
                    $rawType = $data['type'] ?? null;
                    $type = is_string($rawType) ? $rawType : 'library';
                    $rawExtra = $data['extra'] ?? null;
                    if (is_array($rawExtra)) {
                        foreach ($rawExtra as $key => $value) {
                            if (is_string($key)) {
                                $extra[$key] = $value;
                            }
                        }
                    }
                }
            }

            yield new PackageInfo($name, $installPath, $version, $type, $extra);
        }
    }
}
