<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin;

/**
 * Generates and updates AGENTS.md file with skill registry.
 *
 * Creates openskills-compatible XML structure for AI agent skill discovery.
 */
final class AgentsMdGenerator
{
    private const SKILLS_START_MARKER = '<!-- SKILLS_TABLE_START -->';
    private const SKILLS_END_MARKER = '<!-- SKILLS_TABLE_END -->';

    /**
     * Generate skills XML content.
     *
     * @param array<int, array{name: string, description: string, location: string}> $skills
     */
    public function generateSkillsXml(array $skills): string
    {
        // Sort skills alphabetically by name
        usort($skills, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $xml = '<skills_system priority="1">' . "\n\n";
        $xml .= '## Available Skills' . "\n\n";
        $xml .= self::SKILLS_START_MARKER . "\n";
        $xml .= '<usage>' . "\n";
        $xml .= 'When users ask you to perform tasks, check if any of the available skills below can help complete the task more effectively. Skills provide specialized capabilities and domain knowledge.' . "\n\n";
        $xml .= 'How to use skills:' . "\n";
        $xml .= '- Invoke: Bash("composer read-skill <skill-name>")' . "\n";
        $xml .= '- The skill content will load with detailed instructions on how to complete the task' . "\n";
        $xml .= '- Base directory provided in output for resolving bundled resources (references/, scripts/, assets/)' . "\n\n";
        $xml .= 'Usage notes:' . "\n";
        $xml .= '- Only use skills listed in <available_skills> below' . "\n";
        $xml .= '- Do not invoke a skill that is already loaded in your context' . "\n";
        $xml .= '- Each skill invocation is stateless' . "\n";
        $xml .= '</usage>' . "\n\n";
        $xml .= '<available_skills>' . "\n";

        if (count($skills) > 0) {
            $xml .= "\n";
            foreach ($skills as $skill) {
                $xml .= '<skill>' . "\n";
                $xml .= '<name>' . htmlspecialchars($skill['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</name>' . "\n";
                $xml .= '<description>' . htmlspecialchars($skill['description'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</description>' . "\n";
                $xml .= '<location>' . htmlspecialchars($skill['location'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</location>' . "\n";
                $xml .= '</skill>' . "\n";

                // Add double newline between skills (but not after last one)
                if ($skill !== end($skills)) {
                    $xml .= "\n";
                }
            }
        }

        $xml .= "\n" . '</available_skills>' . "\n";
        $xml .= self::SKILLS_END_MARKER . "\n\n";
        $xml .= '</skills_system>';

        return $xml;
    }

    /**
     * Update AGENTS.md file with generated skills content.
     *
     * @param array<int, array{name: string, description: string, location: string}> $skills
     * @throws \RuntimeException If file operations fail
     */
    public function updateAgentsMd(string $agentsMdPath, array $skills): void
    {
        $skillsXml = $this->generateSkillsXml($skills);

        // Read existing file or create new content
        if (file_exists($agentsMdPath)) {
            $content = file_get_contents($agentsMdPath);
            if ($content === false) {
                throw new \RuntimeException(sprintf('Failed to read AGENTS.md at: %s', $agentsMdPath));
            }

            // Replace existing skills_system block
            $pattern = '/<skills_system[^>]*>.*?<\/skills_system>/s';
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $skillsXml, $content);
            } else {
                // No existing block, append to end
                $content = rtrim($content) . "\n\n" . $skillsXml . "\n";
            }
        } else {
            // Create new file with skills content
            $content = $skillsXml . "\n";
        }

        // Atomic write: write to temp file then rename
        $tempPath = $agentsMdPath . '.tmp';
        if (file_put_contents($tempPath, $content) === false) {
            throw new \RuntimeException(sprintf('Failed to write temporary file: %s', $tempPath));
        }

        if (!rename($tempPath, $agentsMdPath)) {
            @unlink($tempPath);
            throw new \RuntimeException(sprintf('Failed to rename temporary file to: %s', $agentsMdPath));
        }
    }
}
