<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Util;

use Netresearch\ComposerAgentSkillPlugin\DirectSkills\SourceEntry;

/**
 * Atomic merge of extra.ai-agent-skills.sources[] into composer.json.
 */
final class ComposerJsonDirectSkillsWriter
{
    public function upsertSource(string $composerJsonPath, SourceEntry $entry): void
    {
        $data = ComposerJsonReader::read($composerJsonPath);
        if ($data === null) {
            throw new \RuntimeException(sprintf('Cannot read composer.json: %s', $composerJsonPath));
        }
        $extra = $data['extra'] ?? [];
        if (!is_array($extra)) {
            $extra = [];
        }
        $block = $extra['ai-agent-skills'] ?? [];
        if (!is_array($block)) {
            $block = [];
        }
        $block['version'] = $block['version'] ?? 1;
        $block['install-dir'] = is_string($block['install-dir'] ?? null)
            ? $block['install-dir']
            : 'vendor/agent-skills/installed';
        $block['sources-dir'] = is_string($block['sources-dir'] ?? null)
            ? $block['sources-dir']
            : 'vendor/agent-skills/sources';
        $block['cache-dir'] = is_string($block['cache-dir'] ?? null)
            ? $block['cache-dir']
            : 'vendor/agent-skills/cache';

        $sources = $block['sources'] ?? [];
        if (!is_array($sources)) {
            $sources = [];
        }
        $newRow = $entry->toArray();
        $replaced = false;
        foreach ($sources as $i => $row) {
            if (is_array($row) && ($row['name'] ?? null) === $entry->name) {
                $sources[$i] = $newRow;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $sources[] = $newRow;
        }
        $block['sources'] = array_values($sources);
        $extra['ai-agent-skills'] = $block;
        $data['extra'] = $extra;

        $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $temp = $composerJsonPath . '.direct-skills.' . bin2hex(random_bytes(6));
        if (file_put_contents($temp, $payload) === false) {
            throw new \RuntimeException(sprintf('Failed writing temp composer.json: %s', $temp));
        }
        if (!rename($temp, $composerJsonPath)) {
            // cleanup of our own freshly-created temp file (random name) on atomic-write rename failure; path is not user input
            @unlink($temp); // nosemgrep: php.lang.security.unlink-use.unlink-use
            throw new \RuntimeException(sprintf('Failed replacing composer.json: %s', $composerJsonPath));
        }
    }

    /**
     * Remove a whole source by id, or drop one skill id from whichever source lists it.
     */
    public function removeSkillOrSource(string $composerJsonPath, string $name, bool $wholeSource): void
    {
        $data = ComposerJsonReader::read($composerJsonPath);
        if ($data === null) {
            throw new \RuntimeException(sprintf('Cannot read composer.json: %s', $composerJsonPath));
        }
        $extra = $data['extra'] ?? [];
        if (!is_array($extra)) {
            return;
        }
        $block = $extra['ai-agent-skills'] ?? null;
        if (!is_array($block) || !isset($block['sources']) || !is_array($block['sources'])) {
            return;
        }
        $sources = $block['sources'];
        if ($wholeSource) {
            $sources = array_values(array_filter($sources, static function ($row) use ($name): bool {
                return !is_array($row) || ($row['name'] ?? null) !== $name;
            }));
        } else {
            $newSources = [];
            foreach ($sources as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $skills = $row['skills'] ?? [];
                if (!is_array($skills)) {
                    $newSources[] = $row;
                    continue;
                }
                $skills = array_values(array_filter($skills, static fn ($s) => $s !== $name));
                if ($skills === []) {
                    continue;
                }
                $row['skills'] = $skills;
                $newSources[] = $row;
            }
            $sources = $newSources;
        }
        $block['sources'] = $sources;
        $extra['ai-agent-skills'] = $block;
        $data['extra'] = $extra;

        $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $temp = $composerJsonPath . '.direct-skills.' . bin2hex(random_bytes(6));
        if (file_put_contents($temp, $payload) === false) {
            throw new \RuntimeException(sprintf('Failed writing temp composer.json: %s', $temp));
        }
        if (!rename($temp, $composerJsonPath)) {
            // cleanup of our own freshly-created temp file (random name) on atomic-write rename failure; path is not user input
            @unlink($temp); // nosemgrep: php.lang.security.unlink-use.unlink-use
            throw new \RuntimeException(sprintf('Failed replacing composer.json: %s', $composerJsonPath));
        }
    }
}
