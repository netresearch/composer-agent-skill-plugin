<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Util;

final class ComposerJsonReader
{
    /**
     * @return array<string, mixed>|null
     */
    public static function read(string $composerJsonPath): ?array
    {
        if (!is_file($composerJsonPath)) {
            return null;
        }
        $json = file_get_contents($composerJsonPath);
        if ($json === false) {
            return null;
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return $data;
        } catch (\JsonException) {
            return null;
        }
    }
}
