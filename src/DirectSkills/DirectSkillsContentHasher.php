<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\DirectSkills;

/**
 * Stable SHA-256 over normalized intent (sources only; no trust).
 */
final class DirectSkillsContentHasher
{
    public function hashConfig(DirectSkillsConfig $config): string
    {
        $normalized = $this->normalize($config->toHashableArray());

        return hash('sha256', $normalized);
    }

    /**
     * @param array<string, mixed> $extraFull Full composer.json extra section (may contain ai-agent-skills.trust)
     */
    public function hashFromExtraArray(array $extraFull): string
    {
        $block = $extraFull['ai-agent-skills'] ?? null;
        if (!is_array($block)) {
            return hash('sha256', '{}');
        }
        unset($block['trust']);
        $cfg = DirectSkillsConfig::tryFromExtra(['ai-agent-skills' => $block]);
        if ($cfg === null) {
            return hash('sha256', '{}');
        }

        return $this->hashConfig($cfg);
    }

    /**
     * JSON with sorted keys at all levels (recursive ksort).
     *
     * @param array<string, mixed> $data
     */
    private function normalize(array $data): string
    {
        $this->recursiveKsort($data);

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $array
     */
    private function recursiveKsort(array &$array): void
    {
        foreach ($array as $k => &$v) {
            if (is_array($v)) {
                /** @var array<string, mixed> $v */
                $this->recursiveKsort($v);
            }
        }
        unset($v);
        ksort($array);
    }
}
