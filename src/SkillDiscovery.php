<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin;

use Composer\InstalledVersions;
use Composer\IO\IOInterface;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use Netresearch\ComposerAgentSkillPlugin\Trust\TrustState;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Discovers AI agent skills from installed Composer packages.
 *
 * Iterates all installed packages and selects those that declare skills
 * (either via extra.ai-agent-skill or the legacy type: ai-agent-skill).
 */
final class SkillDiscovery
{
    private const TYPE_AI_AGENT_SKILL = 'ai-agent-skill';
    private const DEFAULT_SKILL_FILE = 'SKILL.md';
    private const EXTRA_KEY = 'ai-agent-skill';

    /** @var array<int, string> */
    private array $warnings = [];

    public function __construct(
        private readonly IOInterface $io,
        private readonly ?PackageProvider $packages = null,
        private readonly ?SkillTrustManager $trust = null,
    ) {
    }

    /**
     * Discover all skills from installed packages.
     *
     * Pure: never prompts, never mutates state. Each returned skill carries
     * a trust_state field (allowed / denied / pending) populated by reading
     * the trust manager — but no prompt is fired. Gating happens at the
     * install/update boundary in SkillPlugin::updateAgentsMd().
     *
     * @return list<array{name: string, description: string, location: string, package: string, version: string, file: string, trust_state: TrustState}>
     */
    public function discoverAllSkills(): array
    {
        $this->warnings = [];
        $skills = [];
        $skillNames = [];

        $iter = $this->packages !== null
            ? $this->packages->iterAllPackages()
            : $this->iterByType();

        foreach ($iter as $pkg) {
            if (!$pkg->declaresSkills()) {
                continue;
            }

            $packageSkills = $this->discoverSkillsFromPackage($pkg->name, $pkg->installPath, $pkg->version);
            $trustState = $this->resolveTrustState($pkg->name);

            foreach ($packageSkills as $skill) {
                $skill['trust_state'] = $trustState;

                // Handle duplicate skill names (last wins + warning)
                if (isset($skillNames[$skill['name']])) {
                    $previousPackage = $skillNames[$skill['name']];
                    $this->addWarning(
                        $pkg->name,
                        sprintf(
                            "Duplicate skill name '%s' found. Previously defined in %s.\n" .
                            "                   Using skill from %s (last one wins).",
                            $skill['name'],
                            $previousPackage,
                            $pkg->name
                        )
                    );
                }

                $skillNames[$skill['name']] = $pkg->name;
                $skills[$skill['name']] = $skill;
            }
        }

        $this->outputWarnings();

        // Return values sorted by name (array keys are already sorted due to how we build it)
        ksort($skills);
        return array_values($skills);
    }

    private function resolveTrustState(string $packageName): TrustState
    {
        if ($this->trust === null) {
            return TrustState::Allowed; // legacy callers without a trust manager get the old behavior
        }
        if (!$this->trust->hasDecision($packageName)) {
            return TrustState::Pending;
        }
        return $this->trust->isAllowed($packageName) ? TrustState::Allowed : TrustState::Denied;
    }

    /**
     * Legacy iteration path used when no PackageProvider is injected.
     *
     * Preserves backward compatibility for callers that haven't been updated
     * to inject a provider — yields packages tagged type: ai-agent-skill via
     * the static InstalledVersions API. This fallback path does not read each
     * package's composer.json, so PackageInfo::extra is always empty and
     * skill paths fall back to the SKILL.md root convention.
     *
     * @return iterable<PackageInfo>
     */
    private function iterByType(): iterable
    {
        foreach (InstalledVersions::getInstalledPackagesByType(self::TYPE_AI_AGENT_SKILL) as $name) {
            $path = InstalledVersions::getInstallPath($name);
            $version = InstalledVersions::getPrettyVersion($name);
            if ($path === null || $version === null) {
                continue;
            }
            yield new PackageInfo(
                name: $name,
                installPath: $path,
                version: $version,
                type: self::TYPE_AI_AGENT_SKILL,
                extra: [],
            );
        }
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

        $packageRealPath = realpath($installPath);

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

            // Defense-in-depth: even after rejecting '..' segments, a symlink inside
            // the package could point outside it. Verify the resolved file is rooted
            // at the resolved package directory. Fail CLOSED — if either realpath()
            // returns false (broken symlink, open_basedir, permission denied),
            // we skip rather than allow an unverified path through.
            $resolvedFile = realpath($absolutePath);
            if ($packageRealPath === false || $resolvedFile === false) {
                $this->addWarning(
                    $packageName,
                    sprintf(
                        "Skill path '%s' could not be resolved for safety check (broken symlink or restricted filesystem). Skipping.",
                        $relativePath
                    )
                );
                continue;
            }
            $boundary = $packageRealPath . DIRECTORY_SEPARATOR;
            if (!str_starts_with($resolvedFile . DIRECTORY_SEPARATOR, $boundary)) {
                $this->addWarning(
                    $packageName,
                    sprintf(
                        "Skill path '%s' resolves outside the package directory (symlink escape).",
                        $relativePath
                    )
                );
                continue;
            }

            $skillData = $this->parseSkillFile($packageName, $absolutePath, $relativePath);
            if ($skillData !== null) {
                // Base directory is the directory containing SKILL.md
                $baseDirectory = dirname($absolutePath);
                $realBaseDir = realpath($baseDirectory);
                $skillData['location'] = str_replace(DIRECTORY_SEPARATOR, '/', $realBaseDir !== false ? $realBaseDir : $baseDirectory);
                $skillData['package'] = $packageName;
                $skillData['version'] = $version;
                $skillData['file'] = basename($relativePath);
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
        if (!is_array($extra) || !isset($extra[self::EXTRA_KEY])) {
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
            if ($this->isUnsafeRelativePath($config)) {
                $this->addWarning(
                    $packageName,
                    sprintf(
                        "Path traversal '..' rejected in 'extra.ai-agent-skill': '%s'.\n" .
                        "                   Skill paths must stay within the package directory.",
                        $config
                    )
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
                if ($this->isUnsafeRelativePath($path)) {
                    $this->addWarning(
                        $packageName,
                        sprintf(
                            "Path traversal '..' rejected in 'extra.ai-agent-skill': '%s'.\n" .
                            "                   Skill paths must stay within the package directory.",
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
     * Check if a relative path contains traversal segments (..).
     *
     * Reject any path where any segment (split by / or \) is exactly "..".
     * This blocks both leading "../foo" and embedded "subdir/../escape" forms.
     * "foo..bar" inside a filename component is safe and not flagged.
     */
    private function isUnsafeRelativePath(string $path): bool
    {
        $segments = preg_split('#[/\\\\]+#', $path);
        if ($segments === false) {
            return true;
        }
        foreach ($segments as $segment) {
            if ($segment === '..') {
                return true;
            }
        }
        return false;
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

        // Narrow to <string, mixed> shape — non-string keys would already fail validation
        // but PHPStan needs the explicit narrowing to call validateFrontmatter().
        $stringKeyed = [];
        foreach ($frontmatter as $key => $value) {
            if (is_string($key)) {
                $stringKeyed[$key] = $value;
            }
        }

        $validation = $this->validateFrontmatter($stringKeyed);
        if ($validation !== null) {
            $this->addWarning(
                $packageName,
                sprintf("Invalid frontmatter in '%s': %s", $relativePath, $validation)
            );
            return null;
        }

        // validateFrontmatter() already verified name and description are non-empty strings.
        $name = $stringKeyed['name'];
        $description = $stringKeyed['description'];
        if (!is_string($name) || !is_string($description)) {
            return null; // unreachable after validateFrontmatter, but PHPStan needs the narrow
        }

        return [
            'name' => $name,
            'description' => $description,
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
