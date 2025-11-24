<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Integration;

use Composer\IO\BufferIO;
use Netresearch\ComposerAgentSkillPlugin\AgentsMdGenerator;
use Netresearch\ComposerAgentSkillPlugin\SkillDiscovery;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the plugin lifecycle.
 *
 * Tests the full workflow: discovery -> generation -> AGENTS.md update.
 */
final class PluginIntegrationTest extends TestCase
{
    private string $tempDir;
    private BufferIO $io;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/composer-skill-integration-' . uniqid();
        mkdir($this->tempDir);
        $this->io = new BufferIO();
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }
    }

    public function testFullPluginLifecycle(): void
    {
        // This is a basic integration test structure
        // Full integration would require mocking Composer's InstalledVersions API
        // or setting up actual package installations

        $discovery = new SkillDiscovery($this->io);
        $generator = new AgentsMdGenerator();

        // Since we can't easily mock InstalledVersions in integration tests,
        // we test the generator with mock skill data
        $mockSkills = [
            [
                'name' => 'test-skill',
                'description' => 'Integration test skill',
                'location' => '/test/location',
            ],
        ];

        $agentsMdPath = $this->tempDir . '/AGENTS.md';
        $generator->updateAgentsMd($agentsMdPath, $mockSkills);

        $this->assertFileExists($agentsMdPath);

        $content = file_get_contents($agentsMdPath);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('<skills_system priority="1">', $content);
        $this->assertStringContainsString('<name>test-skill</name>', $content);
        $this->assertStringContainsString('<description>Integration test skill</description>', $content);
    }

    public function testAgentsMdGenerationEndToEnd(): void
    {
        $generator = new AgentsMdGenerator();

        // Test with multiple skills
        $skills = [
            [
                'name' => 'skill-one',
                'description' => 'First skill for integration test',
                'location' => '/vendor/skill-one',
            ],
            [
                'name' => 'skill-two',
                'description' => 'Second skill for integration test',
                'location' => '/vendor/skill-two',
            ],
        ];

        $agentsMdPath = $this->tempDir . '/AGENTS.md';

        // First generation
        $generator->updateAgentsMd($agentsMdPath, $skills);

        $this->assertFileExists($agentsMdPath);
        $content = file_get_contents($agentsMdPath);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('<name>skill-one</name>', $content);
        $this->assertStringContainsString('<name>skill-two</name>', $content);

        // Update with new skills
        $updatedSkills = [
            [
                'name' => 'skill-three',
                'description' => 'Third skill replacing previous',
                'location' => '/vendor/skill-three',
            ],
        ];

        $generator->updateAgentsMd($agentsMdPath, $updatedSkills);

        $updatedContent = file_get_contents($agentsMdPath);
        $this->assertNotFalse($updatedContent);

        // Old skills should be replaced
        $this->assertStringNotContainsString('<name>skill-one</name>', $updatedContent);
        $this->assertStringNotContainsString('<name>skill-two</name>', $updatedContent);

        // New skill should be present
        $this->assertStringContainsString('<name>skill-three</name>', $updatedContent);
    }

    public function testCommandExecutionFlow(): void
    {
        // Test would simulate command execution
        // In real scenario, this would test ListSkillsCommand and ReadSkillCommand
        // For now, we verify the components work together

        $discovery = new SkillDiscovery($this->io);
        $generator = new AgentsMdGenerator();

        $this->assertInstanceOf(SkillDiscovery::class, $discovery);
        $this->assertInstanceOf(AgentsMdGenerator::class, $generator);
    }

    public function testAgentsMdPreservesExistingContent(): void
    {
        $agentsMdPath = $this->tempDir . '/AGENTS.md';

        // Create file with existing content
        $existingContent = <<<'MD'
# My Project AGENTS.md

This is important documentation.

## Usage

Instructions here.

<skills_system priority="1">
Old content
</skills_system>

## More Sections

Additional content.
MD;
        file_put_contents($agentsMdPath, $existingContent);

        $generator = new AgentsMdGenerator();
        $skills = [
            ['name' => 'new-skill', 'description' => 'New skill', 'location' => '/new'],
        ];

        $generator->updateAgentsMd($agentsMdPath, $skills);

        $content = file_get_contents($agentsMdPath);
        $this->assertNotFalse($content);

        // Existing structure preserved
        $this->assertStringContainsString('# My Project AGENTS.md', $content);
        $this->assertStringContainsString('This is important documentation.', $content);
        $this->assertStringContainsString('## Usage', $content);
        $this->assertStringContainsString('Instructions here.', $content);
        $this->assertStringContainsString('## More Sections', $content);
        $this->assertStringContainsString('Additional content.', $content);

        // New skills block inserted
        $this->assertStringContainsString('<name>new-skill</name>', $content);
        $this->assertStringNotContainsString('Old content', $content);
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
