<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Trust;

use Composer\IO\IOInterface;
use Composer\Json\JsonManipulator;

/**
 * Owns the on-disk representation of the allow-skills trust map.
 *
 * Splits the file-I/O role out of {@see SkillTrustManager} so the manager can
 * focus on policy/orchestration. Implements:
 *
 * - Cross-process locking via a sidecar `.skill-trust.lock` file.
 * - Atomic write: temp file with random suffix then rename.
 * - Merge with existing data: legacy string/array forms of `extra.ai-agent-skill`
 *   are migrated under a `skills` sub-key so a project can be both a skill
 *   provider and a consumer.
 *
 * **Why we don't use `Composer\Config\JsonConfigSource::addProperty`:**
 * the architectural review suggested migrating to Composer's own ConfigSource
 * for in-memory config sync. ConfigSource uses `JsonManipulator::addSubNode`
 * (which we already use) but its file write is plain `file_put_contents` — no
 * temp+rename, no flock. Our hook runs after install/update so there's no
 * in-process re-read to benefit from `Config::merge()`. Keeping the atomic-
 * write path here gives us stronger guarantees than Composer itself.
 */
final class TrustStore
{
    private const EXTRA_KEY     = 'ai-agent-skill';
    private const ALLOW_SUB_KEY = 'allow-skills';

    public function __construct(
        private readonly string $composerJsonPath,
        private readonly IOInterface $io,
    ) {
    }

    public function allowSkillsExists(): bool
    {
        $data = $this->readJson();
        if (!is_array($data)) {
            return false;
        }
        $extra = $data['extra'] ?? null;
        if (!is_array($extra)) {
            return false;
        }
        $skillExtra = $extra[self::EXTRA_KEY] ?? null;
        if (!is_array($skillExtra)) {
            return false;
        }
        return isset($skillExtra[self::ALLOW_SUB_KEY]) && is_array($skillExtra[self::ALLOW_SUB_KEY]);
    }

    /**
     * @return array<string, bool>
     */
    public function loadAllowSkills(): array
    {
        $data = $this->readJson();
        if (!is_array($data)) {
            return [];
        }
        $extra = $data['extra'] ?? null;
        if (!is_array($extra)) {
            return [];
        }
        $skillExtra = $extra[self::EXTRA_KEY] ?? null;
        if (!is_array($skillExtra)) {
            return [];
        }
        $rules = $skillExtra[self::ALLOW_SUB_KEY] ?? [];
        if (!is_array($rules)) {
            return [];
        }
        $clean = [];
        foreach ($rules as $pattern => $allow) {
            if (is_string($pattern) && is_bool($allow)) {
                $clean[$pattern] = $allow;
            }
        }
        return $clean;
    }

    /**
     * @param array<string, bool> $rules
     */
    public function saveAllowSkills(array $rules): void
    {
        $lockHandle = $this->acquireLock();
        try {
            $contents = is_file($this->composerJsonPath)
                ? (string) file_get_contents($this->composerJsonPath)
                : "{\n}\n";
            $merged = $this->mergeWithExisting($contents, $rules);

            $manipulator = new JsonManipulator($contents);
            $manipulator->addSubNode('extra', self::EXTRA_KEY, $merged);
            $newContents = $manipulator->getContents();

            $tempPath = $this->composerJsonPath . '.skill-trust.' . bin2hex(random_bytes(8));
            if (@file_put_contents($tempPath, $newContents) === false) {
                $this->io->writeError(sprintf(
                    '<error>Failed to write trust decisions to %s. Check file permissions.</error>',
                    $this->composerJsonPath,
                ));
                return;
            }
            if (!@rename($tempPath, $this->composerJsonPath)) {
                @unlink($tempPath);
                $this->io->writeError(sprintf(
                    '<error>Failed to atomically replace %s with trust decisions.</error>',
                    $this->composerJsonPath,
                ));
            }
        } finally {
            $this->releaseLock($lockHandle);
        }
    }

    /**
     * @return resource|null
     */
    private function acquireLock(): mixed
    {
        $lockPath = $this->composerJsonPath . '.skill-trust.lock';
        $fp = @fopen($lockPath, 'c+');
        if ($fp === false) {
            return null;
        }
        @flock($fp, LOCK_EX);
        return $fp;
    }

    /**
     * @param resource|null $handle
     */
    private function releaseLock(mixed $handle): void
    {
        if ($handle === null) {
            return;
        }
        @flock($handle, LOCK_UN);
        @fclose($handle);
        @unlink($this->composerJsonPath . '.skill-trust.lock');
    }

    private function readJson(): mixed
    {
        if (!is_file($this->composerJsonPath)) {
            return null;
        }
        return json_decode((string) file_get_contents($this->composerJsonPath), true);
    }

    /**
     * @param array<string, bool> $rules
     * @return array<string, mixed>
     */
    private function mergeWithExisting(string $composerJsonContents, array $rules): array
    {
        $data = json_decode($composerJsonContents, true);
        $existing = null;
        if (is_array($data)) {
            $extra = $data['extra'] ?? null;
            if (is_array($extra)) {
                $existing = $extra[self::EXTRA_KEY] ?? null;
            }
        }

        $merged = [];

        if (is_string($existing)) {
            $merged['skills'] = [$existing];
        } elseif (is_array($existing) && array_is_list($existing)) {
            $merged['skills'] = $existing;
        } elseif (is_array($existing)) {
            foreach ($existing as $key => $value) {
                if (is_string($key)) {
                    $merged[$key] = $value;
                }
            }
        }

        $merged[self::ALLOW_SUB_KEY] = $rules;
        return $merged;
    }
}
