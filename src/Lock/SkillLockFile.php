<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Lock;

/**
 * Root document for composer.skills.lock.
 */
final class SkillLockFile
{
    /**
     * @param list<LockedSkillPackage> $packages
     */
    public function __construct(
        public readonly int $version,
        public readonly string $pluginVersion,
        public readonly string $contentHash,
        public readonly string $generatedAt,
        public readonly array $packages,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $version = $data['version'] ?? null;
        $pluginVersion = $data['plugin-version'] ?? null;
        $contentHash = $data['content-hash'] ?? null;
        $generatedAt = $data['generated-at'] ?? null;
        $packagesRaw = $data['packages'] ?? null;
        if (!is_int($version) || !is_string($pluginVersion) || !is_string($contentHash) || !is_string($generatedAt)) {
            return null;
        }
        if (!is_array($packagesRaw)) {
            return null;
        }
        $packages = [];
        foreach ($packagesRaw as $p) {
            if (!is_array($p)) {
                continue;
            }
            /** @var array<string, mixed> $p */
            $pkg = LockedSkillPackage::fromArray($p);
            if ($pkg !== null) {
                $packages[] = $pkg;
            }
        }

        return new self($version, $pluginVersion, $contentHash, $generatedAt, $packages);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $pkgs = [];
        foreach ($this->packages as $p) {
            $pkgs[] = $p->toArray();
        }

        return [
            'version' => $this->version,
            'plugin-version' => $this->pluginVersion,
            'content-hash' => $this->contentHash,
            'generated-at' => $this->generatedAt,
            'packages' => $pkgs,
        ];
    }
}
