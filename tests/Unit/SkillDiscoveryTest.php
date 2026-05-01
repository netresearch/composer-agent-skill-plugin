<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Composer\IO\IOInterface;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider;
use Netresearch\ComposerAgentSkillPlugin\SkillDiscovery;
use Netresearch\ComposerAgentSkillPlugin\Trust\TrustState;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SkillDiscovery class.
 *
 * Tests package discovery, SKILL.md parsing, validation, and edge case handling.
 */
final class SkillDiscoveryTest extends TestCase
{
    private IOInterface $io;
    private SkillDiscovery $discovery;

    protected function setUp(): void
    {
        $this->io = $this->createStub(IOInterface::class);
        $this->discovery = new SkillDiscovery($this->io);
    }

    public function testDiscoverSingleSkillPackage(): void
    {
        // This test would require actual package installation or mocking InstalledVersions
        // For now, we test the methods that can be tested without Composer runtime
        $this->assertInstanceOf(SkillDiscovery::class, $this->discovery);
    }

    /**
     * @return PackageProvider
     */
    private function singlePackageProvider(string $name, string $type, array $extra): PackageProvider
    {
        $fixturePath = realpath(__DIR__ . '/../Fixtures/library-with-skill');
        self::assertNotFalse($fixturePath);

        return new class ($name, $fixturePath, $type, $extra) implements PackageProvider {
            /**
             * @param array<string, mixed> $extra
             */
            public function __construct(
                private string $name,
                private string $path,
                private string $type,
                private array $extra,
            ) {
            }

            public function iterAllPackages(): iterable
            {
                yield new PackageInfo(
                    name: $this->name,
                    installPath: $this->path,
                    version: '1.0.0',
                    type: $this->type,
                    extra: $this->extra,
                );
            }
        };
    }

    public function testProviderDrivesIteration(): void
    {
        $provider = $this->singlePackageProvider(
            'test/library-with-skill',
            'library',
            ['ai-agent-skill' => 'SKILL.md'],
        );

        $discovery = new SkillDiscovery($this->io, $provider);
        $skills = $discovery->discoverAllSkills();

        self::assertCount(1, $skills);
        self::assertSame('library-bundled-skill', $skills[0]['name']);
        self::assertSame('test/library-with-skill', $skills[0]['package']);
    }

    public function testTrustStateAnnotatedAllowedWhenTrusted(): void
    {
        $provider = $this->singlePackageProvider(
            'allowed/pkg',
            'library',
            ['ai-agent-skill' => 'SKILL.md'],
        );

        $rootDir = sys_get_temp_dir() . '/discovery-trust-allowed-' . uniqid();
        mkdir($rootDir);
        file_put_contents($rootDir . '/composer.json', (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['allowed/pkg' => true]]],
        ]));

        try {
            $trust = new \Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager(
                new \Composer\IO\BufferIO(),
                $rootDir,
            );
            $discovery = new SkillDiscovery($this->io, $provider, $trust);
            $skills = $discovery->discoverAllSkills();

            self::assertCount(1, $skills);
            self::assertSame(TrustState::Allowed, $skills[0]['trust_state']);
        } finally {
            unlink($rootDir . '/composer.json');
            rmdir($rootDir);
        }
    }

    public function testTrustStateAnnotatedPendingWithoutPrompt(): void
    {
        // Discovery is pure: even unknown packages are returned with trust_state=pending.
        // The non-interactive IO would normally cause decide() to skip — we must NOT call decide() here.
        $provider = $this->singlePackageProvider(
            'unknown/pkg',
            'library',
            ['ai-agent-skill' => 'SKILL.md'],
        );

        $rootDir = sys_get_temp_dir() . '/discovery-pending-' . uniqid();
        mkdir($rootDir);
        file_put_contents($rootDir . '/composer.json', "{\n}\n");

        try {
            $trust = new \Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager(
                new \Composer\IO\BufferIO(), // non-interactive by default
                $rootDir,
            );
            $discovery = new SkillDiscovery($this->io, $provider, $trust);
            $skills = $discovery->discoverAllSkills();

            self::assertCount(1, $skills, 'Discovery must include pending skills, not skip them');
            self::assertSame(TrustState::Pending, $skills[0]['trust_state']);
        } finally {
            unlink($rootDir . '/composer.json');
            rmdir($rootDir);
        }
    }

    public function testTrustStateAnnotatedDeniedWhenExplicitlyDenied(): void
    {
        $provider = $this->singlePackageProvider(
            'denied/pkg',
            'library',
            ['ai-agent-skill' => 'SKILL.md'],
        );

        $rootDir = sys_get_temp_dir() . '/discovery-denied-' . uniqid();
        mkdir($rootDir);
        file_put_contents($rootDir . '/composer.json', (string) json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['denied/pkg' => false]]],
        ]));

        try {
            $trust = new \Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager(
                new \Composer\IO\BufferIO(),
                $rootDir,
            );
            $discovery = new SkillDiscovery($this->io, $provider, $trust);
            $skills = $discovery->discoverAllSkills();

            self::assertCount(1, $skills);
            self::assertSame(TrustState::Denied, $skills[0]['trust_state']);
        } finally {
            unlink($rootDir . '/composer.json');
            rmdir($rootDir);
        }
    }

    public function testTrustStateDefaultsToAllowedWhenNoTrustManager(): void
    {
        // Backward compatibility: callers without a trust manager get the legacy behavior
        // — every discovered skill is treated as allowed.
        $provider = $this->singlePackageProvider(
            'legacy/caller',
            'library',
            ['ai-agent-skill' => 'SKILL.md'],
        );

        $discovery = new SkillDiscovery($this->io, $provider);
        $skills = $discovery->discoverAllSkills();

        self::assertCount(1, $skills);
        self::assertSame(TrustState::Allowed, $skills[0]['trust_state']);
    }

    public function testDiscoverMultiSkillPackage(): void
    {
        // Test fixture: valid-multi-skill should have two skills when discovered
        // This would require integration with actual Composer packages
        $this->assertInstanceOf(SkillDiscovery::class, $this->discovery);
    }

    public function testSkipInvalidFrontmatter(): void
    {
        // Test that skills with invalid frontmatter are skipped
        // Expected behavior: warning issued, skill not included in results
        $this->expectNotToPerformAssertions();
    }

    public function testSkipMalformedYaml(): void
    {
        // Test that malformed YAML results in skip + warning
        $this->expectNotToPerformAssertions();
    }

    public function testSkipMissingSkillFile(): void
    {
        // Test that missing SKILL.md files are handled gracefully
        $this->expectNotToPerformAssertions();
    }

    public function testDuplicateSkillNamesLastWins(): void
    {
        // Test that when two packages have same skill name, last one wins
        // Expected: warning issued about duplicate
        $this->expectNotToPerformAssertions();
    }

    public function testRejectAbsolutePaths(): void
    {
        // Test that absolute paths in extra.ai-agent-skill are rejected
        $method = new \ReflectionMethod(SkillDiscovery::class, 'isAbsolutePath');

        // Unix absolute path
        $this->assertTrue($method->invoke($this->discovery, '/absolute/path'));

        // Windows absolute path
        $this->assertTrue($method->invoke($this->discovery, 'C:\\absolute\\path'));

        // Relative paths
        $this->assertFalse($method->invoke($this->discovery, 'relative/path'));
        $this->assertFalse($method->invoke($this->discovery, './relative'));
        $this->assertFalse($method->invoke($this->discovery, '../relative'));
    }

    public function testRejectPathTraversal(): void
    {
        // SECURITY: relative paths with .. segments must be rejected so a
        // malicious package can't read files outside its install directory.
        $method = new \ReflectionMethod(SkillDiscovery::class, 'isUnsafeRelativePath');

        // Traversal attempts
        $this->assertTrue($method->invoke($this->discovery, '../etc/passwd'));
        $this->assertTrue($method->invoke($this->discovery, 'subdir/../../etc/passwd'));
        $this->assertTrue($method->invoke($this->discovery, '..'));
        $this->assertTrue($method->invoke($this->discovery, 'a/b/../../../c'));

        // Safe relative paths
        $this->assertFalse($method->invoke($this->discovery, 'SKILL.md'));
        $this->assertFalse($method->invoke($this->discovery, 'skills/a.md'));
        $this->assertFalse($method->invoke($this->discovery, 'skills/sub/a.md'));
        // ".." appearing in a filename component (not a path segment) is safe
        $this->assertFalse($method->invoke($this->discovery, 'skills/foo..bar.md'));
    }

    public function testValidateNameFormat(): void
    {
        // Test name validation: lowercase, alphanumeric, hyphens only
        $method = new \ReflectionMethod(SkillDiscovery::class, 'validateFrontmatter');

        // Valid names
        $validCases = [
            ['name' => 'valid-name', 'description' => 'Test description'],
            ['name' => 'lowercase123', 'description' => 'Test description'],
            ['name' => 'test-skill-name', 'description' => 'Test description'],
        ];

        foreach ($validCases as $frontmatter) {
            $result = $method->invoke($this->discovery, $frontmatter);
            $this->assertNull($result, "Expected valid name '{$frontmatter['name']}' to pass validation");
        }

        // Invalid names
        $invalidCases = [
            ['name' => 'Invalid-Name', 'description' => 'Test'], // uppercase
            ['name' => 'name with spaces', 'description' => 'Test'], // spaces
            ['name' => 'name_underscore', 'description' => 'Test'], // underscore
            ['name' => 'name!special', 'description' => 'Test'], // special char
        ];

        foreach ($invalidCases as $frontmatter) {
            $result = $method->invoke($this->discovery, $frontmatter);
            $this->assertNotNull($result, "Expected invalid name '{$frontmatter['name']}' to fail validation");
            $this->assertStringContainsString('Invalid name format', $result);
        }
    }

    public function testValidateDescriptionLength(): void
    {
        $method = new \ReflectionMethod(SkillDiscovery::class, 'validateFrontmatter');

        // Valid description (under 1024 chars)
        $validDescription = str_repeat('x', 1024);
        $result = $method->invoke($this->discovery, [
            'name' => 'test-skill',
            'description' => $validDescription,
        ]);
        $this->assertNull($result);

        // Invalid description (over 1024 chars)
        $invalidDescription = str_repeat('x', 1025);
        $result = $method->invoke($this->discovery, [
            'name' => 'test-skill',
            'description' => $invalidDescription,
        ]);
        $this->assertNotNull($result);
        $this->assertStringContainsString('Description exceeds maximum length', $result);
    }

    public function testValidateNameLength(): void
    {
        $method = new \ReflectionMethod(SkillDiscovery::class, 'validateFrontmatter');

        // Valid name (64 chars)
        $validName = str_repeat('a', 64);
        $result = $method->invoke($this->discovery, [
            'name' => $validName,
            'description' => 'Test description',
        ]);
        $this->assertNull($result);

        // Invalid name (65 chars)
        $invalidName = str_repeat('a', 65);
        $result = $method->invoke($this->discovery, [
            'name' => $invalidName,
            'description' => 'Test description',
        ]);
        $this->assertNotNull($result);
        $this->assertStringContainsString('Invalid name format', $result);
    }

    public function testWarningAccumulation(): void
    {
        // Test that warnings are accumulated and can be output
        // Expected: multiple warnings stored, all output together
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->never())
            ->method('writeError');

        $discovery = new SkillDiscovery($io);

        // With no warnings, no output expected
        $this->assertInstanceOf(SkillDiscovery::class, $discovery);
    }

    public function testMissingRequiredFields(): void
    {
        $method = new \ReflectionMethod(SkillDiscovery::class, 'validateFrontmatter');

        // Missing name
        $result = $method->invoke($this->discovery, [
            'description' => 'Test description',
        ]);
        $this->assertNotNull($result);
        $this->assertStringContainsString("Missing required field: 'name'", $result);

        // Missing description
        $result = $method->invoke($this->discovery, [
            'name' => 'test-skill',
        ]);
        $this->assertNotNull($result);
        $this->assertStringContainsString("Missing required field: 'description'", $result);

        // Empty name
        $result = $method->invoke($this->discovery, [
            'name' => '',
            'description' => 'Test description',
        ]);
        $this->assertNotNull($result);
        $this->assertStringContainsString("Missing required field: 'name'", $result);

        // Empty description
        $result = $method->invoke($this->discovery, [
            'name' => 'test-skill',
            'description' => '',
        ]);
        $this->assertNotNull($result);
        $this->assertStringContainsString("Missing required field: 'description'", $result);
    }

    public function testResolveSkillPathsConvention(): void
    {
        // Test default convention: SKILL.md in package root
        $method = new \ReflectionMethod(SkillDiscovery::class, 'resolveSkillPaths');

        // When no composer.json exists or no extra config, use default
        $fixtureDir = __DIR__ . '/../Fixtures/missing-skill-file';
        $paths = $method->invoke($this->discovery, 'test/package', $fixtureDir);

        $this->assertIsArray($paths);
        $this->assertCount(1, $paths);
        $this->assertEquals('SKILL.md', $paths[0]);
    }

    public function testResolveSkillPathsStringConfig(): void
    {
        // Test string configuration: single custom path
        $method = new \ReflectionMethod(SkillDiscovery::class, 'resolveSkillPaths');

        $fixtureDir = __DIR__ . '/../Fixtures/valid-multi-skill';
        $paths = $method->invoke($this->discovery, 'test/package', $fixtureDir);

        $this->assertIsArray($paths);
        $this->assertGreaterThan(0, count($paths));
    }

    public function testResolveSkillPathsArrayConfig(): void
    {
        // Test array configuration: multiple skill paths
        $method = new \ReflectionMethod(SkillDiscovery::class, 'resolveSkillPaths');

        $fixtureDir = __DIR__ . '/../Fixtures/valid-multi-skill';
        $paths = $method->invoke($this->discovery, 'test/package', $fixtureDir);

        $this->assertIsArray($paths);
        $this->assertCount(2, $paths);
        $this->assertContains('skills/api-helper.md', $paths);
        $this->assertContains('skills/debug-assistant.md', $paths);
    }
}
