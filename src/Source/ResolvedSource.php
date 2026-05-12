<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Source;

/**
 * Normalized direct skill source after CLI / resolver input.
 */
final class ResolvedSource
{
    /**
     * @param string|null $path Subdirectory inside repository (forward slashes) or null for whole repo
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $url,
        public readonly ?string $ref,
        public readonly ?string $path,
    ) {
    }
}
