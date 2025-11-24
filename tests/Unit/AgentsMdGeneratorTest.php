<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\AgentsMdGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AgentsMdGenerator class.
 *
 * Tests XML generation, file operations, and openskills format compliance.
 */
final class AgentsMdGeneratorTest extends TestCase
{
    private AgentsMdGenerator $generator;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->generator = new AgentsMdGenerator();
        $this->tempDir = sys_get_temp_dir() . '/composer-skill-test-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function testGenerateSkillsXml(): void
    {
        $skills = [
            [
                'name' => 'database-analyzer',
                'description' => 'Analyze and optimize database schemas',
                'location' => '/vendor/test/db-skill',
            ],
        ];

        $xml = $this->generator->generateSkillsXml($skills);

        $this->assertStringContainsString('<skills_system priority="1">', $xml);
        $this->assertStringContainsString('## Available Skills', $xml);
        $this->assertStringContainsString('<!-- SKILLS_TABLE_START -->', $xml);
        $this->assertStringContainsString('<!-- SKILLS_TABLE_END -->', $xml);
        $this->assertStringContainsString('<skill>', $xml);
        $this->assertStringContainsString('<name>database-analyzer</name>', $xml);
        $this->assertStringContainsString('<description>Analyze and optimize database schemas</description>', $xml);
        $this->assertStringContainsString('<location>/vendor/test/db-skill</location>', $xml);
        $this->assertStringContainsString('</skill>', $xml);
        $this->assertStringContainsString('</skills_system>', $xml);
    }

    public function testSkillsAlphabeticallySorted(): void
    {
        $skills = [
            ['name' => 'zebra-skill', 'description' => 'Z skill', 'location' => '/vendor/z'],
            ['name' => 'alpha-skill', 'description' => 'A skill', 'location' => '/vendor/a'],
            ['name' => 'middle-skill', 'description' => 'M skill', 'location' => '/vendor/m'],
        ];

        $xml = $this->generator->generateSkillsXml($skills);

        // Check that skills appear in alphabetical order
        $posAlpha = strpos($xml, '<name>alpha-skill</name>');
        $posMiddle = strpos($xml, '<name>middle-skill</name>');
        $posZebra = strpos($xml, '<name>zebra-skill</name>');

        $this->assertNotFalse($posAlpha);
        $this->assertNotFalse($posMiddle);
        $this->assertNotFalse($posZebra);
        $this->assertLessThan($posMiddle, $posAlpha, 'alpha-skill should come before middle-skill');
        $this->assertLessThan($posZebra, $posMiddle, 'middle-skill should come before zebra-skill');
    }

    public function testXmlStructureMatchesOpenskills(): void
    {
        $skills = [
            ['name' => 'test-skill', 'description' => 'Test', 'location' => '/test'],
        ];

        $xml = $this->generator->generateSkillsXml($skills);

        // Verify exact openskills structure
        $this->assertMatchesRegularExpression(
            '/<skills_system priority="1">\n\n## Available Skills\n\n<!-- SKILLS_TABLE_START -->/s',
            $xml,
            'XML should start with proper skills_system header'
        );

        $this->assertStringContainsString('<usage>', $xml);
        $this->assertStringContainsString('Bash("composer read-skill <skill-name>")', $xml);
        $this->assertStringContainsString('</usage>', $xml);

        $this->assertStringContainsString('<available_skills>', $xml);
        $this->assertStringContainsString('</available_skills>', $xml);

        // Verify no attributes on skill element
        $this->assertMatchesRegularExpression('/<skill>\n<name>/', $xml, 'skill element should have no attributes');
    }

    public function testUpdateAgentsMdCreatesFile(): void
    {
        $agentsMdPath = $this->tempDir . '/AGENTS.md';
        $skills = [
            ['name' => 'test-skill', 'description' => 'Test skill', 'location' => '/test'],
        ];

        $this->assertFileDoesNotExist($agentsMdPath);

        $this->generator->updateAgentsMd($agentsMdPath, $skills);

        $this->assertFileExists($agentsMdPath);

        $content = file_get_contents($agentsMdPath);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('<skills_system priority="1">', $content);
        $this->assertStringContainsString('<name>test-skill</name>', $content);
    }

    public function testUpdateAgentsMdReplacesBlock(): void
    {
        $agentsMdPath = $this->tempDir . '/AGENTS.md';

        // Create existing file with old skills block
        $existingContent = <<<'MD'
# My Project

Some content here.

<skills_system priority="1">
Old skills content that should be replaced
</skills_system>

More content after.
MD;
        file_put_contents($agentsMdPath, $existingContent);

        $skills = [
            ['name' => 'new-skill', 'description' => 'New skill', 'location' => '/new'],
        ];

        $this->generator->updateAgentsMd($agentsMdPath, $skills);

        $content = file_get_contents($agentsMdPath);
        $this->assertNotFalse($content);

        // Old content should be replaced
        $this->assertStringNotContainsString('Old skills content', $content);

        // New content should be present
        $this->assertStringContainsString('<name>new-skill</name>', $content);

        // Other content should be preserved
        $this->assertStringContainsString('# My Project', $content);
        $this->assertStringContainsString('Some content here.', $content);
        $this->assertStringContainsString('More content after.', $content);
    }

    public function testUpdateAgentsMdPreservesContent(): void
    {
        $agentsMdPath = $this->tempDir . '/AGENTS.md';

        // Create file with content before and after skills block
        $existingContent = <<<'MD'
# Documentation

Introduction text.

<skills_system priority="1">
Old content
</skills_system>

Conclusion text.
MD;
        file_put_contents($agentsMdPath, $existingContent);

        $skills = [
            ['name' => 'skill-a', 'description' => 'Skill A', 'location' => '/a'],
        ];

        $this->generator->updateAgentsMd($agentsMdPath, $skills);

        $content = file_get_contents($agentsMdPath);
        $this->assertNotFalse($content);

        // Verify content preservation
        $this->assertStringContainsString('# Documentation', $content);
        $this->assertStringContainsString('Introduction text.', $content);
        $this->assertStringContainsString('Conclusion text.', $content);

        // Verify new skills inserted
        $this->assertStringContainsString('<name>skill-a</name>', $content);
    }

    public function testMultipleSkillsFormatting(): void
    {
        $skills = [
            ['name' => 'skill-1', 'description' => 'First skill', 'location' => '/path1'],
            ['name' => 'skill-2', 'description' => 'Second skill', 'location' => '/path2'],
            ['name' => 'skill-3', 'description' => 'Third skill', 'location' => '/path3'],
        ];

        $xml = $this->generator->generateSkillsXml($skills);

        // Count skill blocks
        $skillCount = substr_count($xml, '<skill>');
        $this->assertEquals(3, $skillCount, 'Should have exactly 3 skill blocks');

        // Verify double newlines between skills
        $this->assertStringContainsString("</skill>\n\n<skill>", $xml, 'Should have double newline between skills');

        // Verify no double newline after last skill
        $this->assertStringContainsString("</skill>\n\n</available_skills>", $xml, 'Should have single newline after last skill before closing tag');
    }

    public function testXmlEscaping(): void
    {
        $skills = [
            [
                'name' => 'test-skill',
                'description' => 'Test with <special> & "quoted" characters',
                'location' => '/path/with/&/ampersand',
            ],
        ];

        $xml = $this->generator->generateSkillsXml($skills);

        // Verify HTML entities are escaped
        $this->assertStringContainsString('&lt;special&gt;', $xml);
        $this->assertStringContainsString('&amp;', $xml);
        $this->assertStringContainsString('&quot;', $xml);

        // Verify original unescaped versions are not present
        $this->assertStringNotContainsString('<special>', $xml);
    }

    public function testEmptySkillsList(): void
    {
        $skills = [];

        $xml = $this->generator->generateSkillsXml($skills);

        // Should still have valid structure
        $this->assertStringContainsString('<skills_system priority="1">', $xml);
        $this->assertStringContainsString('<available_skills>', $xml);
        $this->assertStringContainsString('</available_skills>', $xml);

        // Should not contain any skill blocks
        $this->assertStringNotContainsString('<skill>', $xml);
    }

    public function testAtomicFileWrite(): void
    {
        $agentsMdPath = $this->tempDir . '/AGENTS.md';
        $skills = [
            ['name' => 'test', 'description' => 'Test', 'location' => '/test'],
        ];

        // Temporary file should not exist before operation
        $this->assertFileDoesNotExist($agentsMdPath . '.tmp');

        $this->generator->updateAgentsMd($agentsMdPath, $skills);

        // Temporary file should not exist after operation (atomic rename)
        $this->assertFileDoesNotExist($agentsMdPath . '.tmp');

        // Final file should exist
        $this->assertFileExists($agentsMdPath);
    }
}
