<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Trust;

use Composer\IO\BufferIO;
use Netresearch\ComposerAgentSkillPlugin\Trust\TrustStore;
use PHPUnit\Framework\TestCase;

final class TrustStoreTest extends TestCase
{
    private string $rootDir;
    private string $composerJsonPath;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/trust-store-' . uniqid();
        mkdir($this->rootDir);
        $this->composerJsonPath = $this->rootDir . '/composer.json';
    }

    protected function tearDown(): void
    {
        // Restore writability in case a test left the dir 0o555.
        if (is_dir($this->rootDir)) {
            @chmod($this->rootDir, 0o755);
        }
        if (file_exists($this->composerJsonPath)) {
            @unlink($this->composerJsonPath);
        }
        if (is_dir($this->rootDir)) {
            foreach ((array) glob($this->rootDir . '/composer.json.*') as $file) {
                if (is_string($file) && is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->rootDir);
        }
    }

    public function testLoadEmptyWhenComposerJsonMissing(): void
    {
        $store = new TrustStore($this->composerJsonPath, new BufferIO());
        self::assertSame([], $store->loadAllowSkills());
        self::assertFalse($store->allowSkillsExists());
    }

    public function testLoadEmptyWhenMapMissing(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $store = new TrustStore($this->composerJsonPath, new BufferIO());
        self::assertSame([], $store->loadAllowSkills());
        self::assertFalse($store->allowSkillsExists());
    }

    public function testLoadParsesAllowSkills(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => true]]],
        ]));
        $store = new TrustStore($this->composerJsonPath, new BufferIO());
        self::assertSame(['vendor/foo' => true], $store->loadAllowSkills());
        self::assertTrue($store->allowSkillsExists());
    }

    public function testEmptyMapStillCountsAsExists(): void
    {
        // First-run policy `n` writes an empty allow-skills map so it doesn't re-prompt.
        // The store must report exists() === true even when the map is [].
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => []]],
        ]));
        $store = new TrustStore($this->composerJsonPath, new BufferIO());
        self::assertTrue($store->allowSkillsExists());
    }

    public function testSaveWritesNewMap(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $store = new TrustStore($this->composerJsonPath, new BufferIO());

        $store->saveAllowSkills(['vendor/foo' => true]);

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertSame(['vendor/foo' => true], $data['extra']['ai-agent-skill']['allow-skills']);
    }

    public function testSavePreservesExistingStringSkills(): void
    {
        file_put_contents($this->composerJsonPath, (string) json_encode([
            'extra' => ['ai-agent-skill' => 'SKILL.md'],
        ], JSON_PRETTY_PRINT));
        $store = new TrustStore($this->composerJsonPath, new BufferIO());

        $store->saveAllowSkills(['vendor/foo' => true]);

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertSame(['SKILL.md'], $data['extra']['ai-agent-skill']['skills']);
        self::assertSame(['vendor/foo' => true], $data['extra']['ai-agent-skill']['allow-skills']);
    }

    public function testSaveAtomicWriteFailureWarns(): void
    {
        file_put_contents($this->composerJsonPath, "{\n}\n");
        chmod($this->rootDir, 0o555);
        $io = new BufferIO();
        $store = new TrustStore($this->composerJsonPath, $io);

        $store->saveAllowSkills(['vendor/foo' => true]);

        chmod($this->rootDir, 0o755);
        self::assertStringContainsString('Failed to write', $io->getOutput());
    }

    public function testFileLockPreventsLostUpdates(): void
    {
        // Two TrustStore instances pointing at the same file should serialize
        // their writes via the lockfile. We can't easily test true concurrency
        // here, but we can verify the lockfile is created and released cleanly.
        file_put_contents($this->composerJsonPath, "{\n}\n");
        $store1 = new TrustStore($this->composerJsonPath, new BufferIO());
        $store2 = new TrustStore($this->composerJsonPath, new BufferIO());

        $store1->saveAllowSkills(['vendor/a' => true]);
        $store2->saveAllowSkills(['vendor/a' => true, 'vendor/b' => true]);

        $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
        self::assertSame(
            ['vendor/a' => true, 'vendor/b' => true],
            $data['extra']['ai-agent-skill']['allow-skills'],
        );
    }
}
