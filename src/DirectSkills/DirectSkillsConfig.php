<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\DirectSkills;

/**
 * Normalized extra.ai-agent-skills block (typed).
 */
final class DirectSkillsConfig
{
    /** @var list<SourceEntry> */
    public readonly array $sources;

    /**
     * @param list<SourceEntry> $sources
     */
    public function __construct(
        public readonly int $version,
        public readonly string $installDir,
        public readonly string $sourcesDir,
        public readonly string $cacheDir,
        array $sources,
    ) {
        $this->sources = $sources;
    }

    public function isEmpty(): bool
    {
        return $this->sources === [];
    }

    /**
     * Raw subtree for hashing (excludes trust — see DirectSkillsContentHasher).
     *
     * @return array<string, mixed>
     */
    public function toHashableArray(): array
    {
        $sources = [];
        foreach ($this->sources as $s) {
            $sources[] = $s->toArray();
        }

        return [
            'version' => $this->version,
            'install-dir' => $this->installDir,
            'sources-dir' => $this->sourcesDir,
            'cache-dir' => $this->cacheDir,
            'sources' => $sources,
        ];
    }

    /**
     * @param array<string, mixed>|null $extraRoot composer.json ["extra"] section
     */
    public static function tryFromExtra(?array $extraRoot): ?self
    {
        if ($extraRoot === null || !isset($extraRoot['ai-agent-skills'])) {
            return null;
        }
        $block = $extraRoot['ai-agent-skills'];
        if (!is_array($block)) {
            return null;
        }
        $version = isset($block['version']) && is_int($block['version']) ? $block['version'] : 1;
        $installDir = is_string($block['install-dir'] ?? null)
            ? $block['install-dir']
            : 'vendor/agent-skills/installed';
        $sourcesDir = is_string($block['sources-dir'] ?? null)
            ? $block['sources-dir']
            : 'vendor/agent-skills/sources';
        $cacheDir = is_string($block['cache-dir'] ?? null)
            ? $block['cache-dir']
            : 'vendor/agent-skills/cache';

        $sourcesIn = $block['sources'] ?? [];
        if (!is_array($sourcesIn)) {
            return null;
        }
        $sources = [];
        foreach ($sourcesIn as $row) {
            if (!is_array($row)) {
                continue;
            }
            /** @var array<string, mixed> $row */
            $entry = SourceEntry::fromArray($row);
            if ($entry !== null) {
                $sources[] = $entry;
            }
        }

        DirectSkillsPathGuard::assertConfigRelativeDir('extra.ai-agent-skills.install-dir', $installDir);
        DirectSkillsPathGuard::assertConfigRelativeDir('extra.ai-agent-skills.sources-dir', $sourcesDir);
        DirectSkillsPathGuard::assertConfigRelativeDir('extra.ai-agent-skills.cache-dir', $cacheDir);

        return new self($version, $installDir, $sourcesDir, $cacheDir, $sources);
    }
}
