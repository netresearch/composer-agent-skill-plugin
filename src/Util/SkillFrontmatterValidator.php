<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Util;

/**
 * Shared validation for SKILL.md name + description (AGENTS.md / XML safety).
 */
final class SkillFrontmatterValidator
{
    /**
     * @return string|null Error message, or null when valid
     */
    public static function validateNameAndDescriptionStrings(string $name, string $description): ?string
    {
        if (!preg_match('/^[a-z0-9-]{1,64}$/', $name)) {
            return sprintf(
                "Invalid name format '%s'. Must be lowercase letters, numbers, and hyphens only (max 64 chars).",
                $name,
            );
        }

        if (strlen($name) > 64) {
            return sprintf("Name '%s' exceeds maximum length of 64 characters.", $name);
        }

        if (strlen($description) > 1024) {
            return sprintf('Description exceeds maximum length of 1024 characters (%d chars).', strlen($description));
        }

        if (preg_match('/[\x00-\x1F\x7F]/u', $description) === 1
            || preg_match('/\x{202A}|\x{202B}|\x{202C}|\x{202D}|\x{202E}|\x{2066}|\x{2067}|\x{2068}|\x{2069}/u', $description) === 1
        ) {
            return 'Description contains control characters or bidi-override codepoints.';
        }

        return null;
    }
}
