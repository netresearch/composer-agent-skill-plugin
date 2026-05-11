<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Util;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Minimal SKILL.md frontmatter parsing (name + description) for direct installs.
 */
final class SkillMarkdownParser
{
    /**
     * @return array{name: string, description: string}|null
     */
    public static function parseNameDescription(string $absolutePath): ?array
    {
        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return null;
        }
        if (!preg_match('/^---\s*\R(.*?)\R---\s*\R/s', $content, $matches)) {
            return null;
        }
        try {
            $frontmatter = Yaml::parse($matches[1]);
        } catch (ParseException) {
            return null;
        }
        if (!is_array($frontmatter)) {
            return null;
        }
        $name = $frontmatter['name'] ?? null;
        $desc = $frontmatter['description'] ?? null;
        if (!is_string($name) || !is_string($desc) || trim($name) === '' || trim($desc) === '') {
            return null;
        }
        if (SkillFrontmatterValidator::validateNameAndDescriptionStrings($name, $desc) !== null) {
            return null;
        }

        return ['name' => $name, 'description' => $desc];
    }
}
