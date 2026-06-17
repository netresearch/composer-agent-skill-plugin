<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Lock;

/**
 * Read / write composer.skills.lock next to composer.json.
 */
final class SkillLockIo
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    public function lockPath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . 'composer.skills.lock';
    }

    public function exists(): bool
    {
        return is_file($this->lockPath());
    }

    public function read(): ?SkillLockFile
    {
        $path = $this->lockPath();
        if (!is_file($path)) {
            return null;
        }
        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return SkillLockFile::fromArray($data);
    }

    public function write(SkillLockFile $lock): void
    {
        $path = $this->lockPath();
        $payload = json_encode($lock->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $temp = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($temp, $payload) === false) {
            throw new \RuntimeException(sprintf('Failed writing temporary skills lock: %s', $temp));
        }
        if (!rename($temp, $path)) {
            // cleanup of our own freshly-created temp file (random name) on atomic-write rename failure; path is not user input
            @unlink($temp); // nosemgrep: php.lang.security.unlink-use.unlink-use
            throw new \RuntimeException(sprintf('Failed replacing skills lock: %s', $path));
        }
    }
}
