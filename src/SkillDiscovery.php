<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin;

use Composer\InstalledVersions;
use Composer\IO\IOInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Discovers AI agent skills from installed Composer packages.
 *
 * Scans all packages with type "ai-agent-skill", reads their SKILL.md files,
 * validates frontmatter, and returns skill metadata for registration.
 */
final class SkillDiscovery
{
    private const TYPE_AI_AGENT_SKILL = 'ai-agent-skill';
    private const DEFAULT_SKILL_FILE = 'SKILL.md';
    private const EXTRA_KEY = 'ai-agent-skill';

    /** @var array<int, string> */
    private array $warnings = [];

    public function __construct(
        private readonly IOInterface $io
    ) {
    }

    /**
     * Discover all skills from installed packages.
     *
     * @return array<int, array{name: string, description: string, location: string, package: string, version: string, file: string}>
     */
    public function discoverAllSkills(): array
    {
        $this->warnings = [];
        $skills = [];
        $skillNames = [];

        $packageNames = InstalledVersions::getInstalledPackagesByType(self::TYPE_AI_AGENT_SKILL);

        foreach ($packageNames as $packageName) {
            $installPath = InstalledVersions::getInstallPath($packageName);
            $version = InstalledVersions::getPrettyVersion($packageName);

            if ($installPath === null || $version === null) {
                continue;
            }

            $packageSkills = $this->discoverSkillsFromPackage($packageName, $installPath, $version);

            foreach ($packageSkills as $skill) {
                // Handle duplicate skill names (last wins + warning)
                if (isset($skillNames[$skill['name']])) {
                    $previousPackage = $skillNames[$skill['name']];
                    $this->addWarning(
                        $packageName,
                        sprintf(
                            "Duplicate skill name '%s' found. Previously defined in %s.\n" .
                            "                   Using skill from %s (last one wins).",
                            $skill['name'],
                            $previousPackage,
                            $packageName
                        )
                    );
                }

                $skillNames[$skill['name']] = $packageName;
                $skills[$skill['name']] = $skill;
            }
        }

        $this->outputWarnings();

        // Return values sorted by name (array keys are already sorted due to how we build it)
        ksort($skills);
        return array_values($skills);
    }

    /**
     * Discover skills from a single package.
     *
     * @return array<int, array{name: string, description: string, location: string, package: string, version: string, file: string}>
     */
    private function discoverSkillsFromPackage(string $packageName, string $installPath, string $version): array
    {
        $skills = [];
        $skillPaths = $this->resolveSkillPaths($packageName, $installPath);

        foreach ($skillPaths as $relativePath) {
            $absolutePath = $installPath . DIRECTORY_SEPARATOR . $relativePath;

            if (!file_exists($absolutePath)) {
                $this->addWarning(
                    $packageName,
                    sprintf(
                        "SKILL.md not found at '%s'.\n" .
                        "                   %s",
                        $relativePath,
                        $relativePath === self::DEFAULT_SKILL_FILE
                            ? 'Expected SKILL.md in package root (convention).'
                            : "Check 'extra.ai-agent-skill' configuration in composer.json."
                    )
                );
                continue;
            }

            $skillData = $this->parseSkillFile($packageName, $absolutePath, $relativePath);
            if ($skillData !== null) {
                // Use realpath to resolve canonical path without traversal (../)
                $realPath = realpath($installPath);
                $skillData['location'] = str_replace(DIRECTORY_SEPARATOR, '/', $realPath !== false ? $realPath : $installPath);
                $skillData['package'] = $packageName;
                $skillData['version'] = $version;
                $skillData['file'] = $relativePath;
                $skills[] = $skillData;
            }
        }

        return $skills;
    }

    /**
     * Resolve skill file paths from package configuration.
     *
     * @return array<int, string>
     */
    private function resolveSkillPaths(string $packageName, string $installPath): array
    {
        $composerJsonPath = $installPath . DIRECTORY_SEPARATOR . 'composer.json';

        if (!file_exists($composerJsonPath)) {
            return [self::DEFAULT_SKILL_FILE];
        }

        $composerData = json_decode((string) file_get_contents($composerJsonPath), true);
        if (!is_array($composerData)) {
            return [self::DEFAULT_SKILL_FILE];
        }

        $extra = $composerData['extra'] ?? [];
        if (!isset($extra[self::EXTRA_KEY])) {
            return [self::DEFAULT_SKILL_FILE];
        }

        $config = $extra[self::EXTRA_KEY];

        // String configuration: single skill path
        if (is_string($config)) {
            if ($this->isAbsolutePath($config)) {
                $this->addWarning(
                    $packageName,
                    "Absolute paths not allowed in 'extra.ai-agent-skill'.\n" .
                    "                   Use relative paths from package root."
                );
                return [];
            }
            return [$config];
        }

        // Array configuration: multiple skill paths
        if (is_array($config)) {
            $paths = [];
            foreach ($config as $path) {
                if (!is_string($path)) {
                    continue;
                }
                if ($this->isAbsolutePath($path)) {
                    $this->addWarning(
                        $packageName,
                        sprintf(
                            "Absolute path '%s' not allowed in 'extra.ai-agent-skill'.\n" .
                            "                   Use relative paths from package root.",
                            $path
                        )
                    );
                    continue;
                }
                $paths[] = $path;
            }
            return $paths;
        }

        return [self::DEFAULT_SKILL_FILE];
    }

    /**
     * Check if a path is absolute.
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix absolute path starts with /
        // Windows absolute path matches C:\ or similar
        return str_starts_with($path, '/') ||
               (strlen($path) > 1 && $path[1] === ':');
    }

    /**
     * Parse SKILL.md file and extract frontmatter.
     *
     * @return array{name: string, description: string}|null
     */
    private function parseSkillFile(string $packageName, string $filePath, string $relativePath): ?array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Extract YAML frontmatter between --- delimiters
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            $this->addWarning(
                $packageName,
                sprintf("No YAML frontmatter found in '%s'.", $relativePath)
            );
            return null;
        }

        $yamlContent = $matches[1];

        try {
            $frontmatter = Yaml::parse($yamlContent);
        } catch (ParseException $e) {
            $this->addWarning(
                $packageName,
                sprintf(
                    "Malformed YAML in '%s':\n" .
                    "                   %s",
                    $relativePath,
                    $e->getMessage()
                )
            );
            return null;
        }

        if (!is_array($frontmatter)) {
            $this->addWarning(
                $packageName,
                sprintf("Invalid frontmatter in '%s': Expected array.", $relativePath)
            );
            return null;
        }

        $validation = $this->validateFrontmatter($frontmatter);
        if ($validation !== null) {
            $this->addWarning(
                $packageName,
                sprintf("Invalid frontmatter in '%s': %s", $relativePath, $validation)
            );
            return null;
        }

        return [
            'name' => $frontmatter['name'],
            'description' => $frontmatter['description'],
        ];
    }

    /**
     * Validate frontmatter fields.
     *
     * @param array<string, mixed> $frontmatter
     * @return string|null Error message or null if valid
     */
    private function validateFrontmatter(array $frontmatter): ?string
    {
        // Check required fields
        if (!isset($frontmatter['name']) || !is_string($frontmatter['name']) || trim($frontmatter['name']) === '') {
            return "Missing required field: 'name'";
        }

        if (!isset($frontmatter['description']) || !is_string($frontmatter['description']) || trim($frontmatter['description']) === '') {
            return "Missing required field: 'description'";
        }

        $name = $frontmatter['name'];
        $description = $frontmatter['description'];

        // Validate name format (kebab-case)
        if (!preg_match('/^[a-z0-9-]{1,64}$/', $name)) {
            return sprintf(
                "Invalid name format '%s'. Must be lowercase letters, numbers, and hyphens only (max 64 chars).",
                $name
            );
        }

        // Validate name length
        if (strlen($name) > 64) {
            return sprintf("Name '%s' exceeds maximum length of 64 characters.", $name);
        }

        // Validate description length
        if (strlen($description) > 1024) {
            return sprintf("Description exceeds maximum length of 1024 characters (%d chars).", strlen($description));
        }

        return null;
    }

    /**
     * Add a warning message.
     */
    private function addWarning(string $packageName, string $message): void
    {
        $this->warnings[] = sprintf('  [%s] %s', $packageName, $message);
    }

    /**
     * Output all collected warnings.
     */
    private function outputWarnings(): void
    {
        if (count($this->warnings) === 0) {
            return;
        }

        $this->io->writeError('');
        $this->io->writeError('<warning>AI Agent Skill Plugin Warnings:</warning>');
        foreach ($this->warnings as $warning) {
            $this->io->writeError($warning);
        }
        $this->io->writeError('');
    }
}
