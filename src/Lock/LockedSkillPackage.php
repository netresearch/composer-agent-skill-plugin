<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Lock;

/**
 * One package entry in composer.skills.lock.
 *
 * @phpstan-type MetadataShape array{description?: string, schema?: string}
 */
final class LockedSkillPackage
{
    /**
     * @param array<string, string>|null $metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly string $source,
        public readonly string $type,
        public readonly ?string $url,
        public readonly ?string $ref,
        public readonly string $commit,
        public readonly string $pathInSource,
        public readonly string $checksum,
        public readonly string $installPath,
        public readonly ?array $metadata = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): ?self
    {
        $name = $row['name'] ?? null;
        $source = $row['source'] ?? null;
        $type = $row['type'] ?? null;
        $commit = $row['commit'] ?? null;
        $pathInSource = $row['path'] ?? null;
        $checksum = $row['checksum'] ?? null;
        $installPath = $row['install-path'] ?? null;
        if (!is_string($name) || !is_string($source) || !is_string($type)) {
            return null;
        }
        if (!is_string($commit) || !is_string($pathInSource) || !is_string($checksum) || !is_string($installPath)) {
            return null;
        }
        $url = isset($row['url']) && is_string($row['url']) ? $row['url'] : null;
        $ref = isset($row['ref']) && is_string($row['ref']) ? $row['ref'] : null;
        $meta = null;
        if (isset($row['metadata']) && is_array($row['metadata'])) {
            $meta = [];
            foreach ($row['metadata'] as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $meta[$k] = $v;
                }
            }
            if ($meta === []) {
                $meta = null;
            }
        }

        return new self($name, $source, $type, $url, $ref, $commit, $pathInSource, $checksum, $installPath, $meta);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $a = [
            'name' => $this->name,
            'source' => $this->source,
            'type' => $this->type,
            'commit' => $this->commit,
            'path' => $this->pathInSource,
            'checksum' => $this->checksum,
            'install-path' => $this->installPath,
        ];
        if ($this->url !== null) {
            $a['url'] = $this->url;
        }
        if ($this->ref !== null) {
            $a['ref'] = $this->ref;
        }
        if ($this->metadata !== null) {
            $a['metadata'] = $this->metadata;
        }

        return $a;
    }
}
