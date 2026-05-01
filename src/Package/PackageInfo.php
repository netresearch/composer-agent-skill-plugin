<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Package;

final readonly class PackageInfo
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $name,
        public string $installPath,
        public string $version,
        public string $type,
        public array $extra,
    ) {
    }

    public function declaresSkills(): bool
    {
        return $this->type === 'ai-agent-skill' || isset($this->extra['ai-agent-skill']);
    }
}
