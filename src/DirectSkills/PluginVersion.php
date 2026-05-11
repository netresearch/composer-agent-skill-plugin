<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\DirectSkills;

/**
 * Best-effort plugin version string for composer.skills.lock metadata.
 */
final class PluginVersion
{
    public static function detect(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $name = 'netresearch/composer-agent-skill-plugin';
                if (\Composer\InstalledVersions::isInstalled($name, false)) {
                    return \Composer\InstalledVersions::getPrettyVersion($name)
                        ?? \Composer\InstalledVersions::getVersion($name)
                        ?? 'unknown';
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return 'unknown';
    }
}
