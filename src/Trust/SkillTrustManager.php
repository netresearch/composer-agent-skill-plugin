<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Trust;

use Composer\IO\IOInterface;
use Composer\Json\JsonManipulator;
use Composer\Package\BasePackage;

final class SkillTrustManager
{
    private const EXTRA_KEY     = 'ai-agent-skill';
    private const ALLOW_SUB_KEY = 'allow-skills';

    /** @var array<string, bool>|null Cache of root config rules. */
    private ?array $configRules = null;

    /** @var array<string, bool> Session-only allow decisions. */
    private array $sessionRules = [];

    public function __construct(
        private readonly IOInterface $io,
        private readonly string $rootDir,
    ) {
    }

    public function hasDecision(string $packageName): bool
    {
        if (isset($this->sessionRules[$packageName])) {
            return true;
        }
        return $this->matchPattern($packageName) !== null;
    }

    public function isAllowed(string $packageName): bool
    {
        if (isset($this->sessionRules[$packageName])) {
            return $this->sessionRules[$packageName];
        }
        return $this->matchPattern($packageName) === true;
    }

    /**
     * Resolve trust for a package, prompting the user if the decision is unknown.
     *
     * Only call from the install/update boundary — never from informational paths
     * like `composer list-skills`. Mirrors Composer's PluginManager pattern.
     */
    public function decide(string $packageName): bool
    {
        if ($this->hasDecision($packageName)) {
            return $this->isAllowed($packageName);
        }

        if (!$this->io->isInteractive()) {
            $this->io->writeError(sprintf(
                '<warning>Skipping skills from "%s" — package is not in extra.ai-agent-skill.allow-skills.</warning>',
                $packageName,
            ));
            $this->io->writeError(sprintf(
                '  Run: <info>composer config --json --merge extra.ai-agent-skill.allow-skills \'{"%s": true}\'</info>',
                $packageName,
            ));
            return false;
        }

        return $this->prompt($packageName);
    }

    /**
     * Seed allow-skills with currently-installed packages.
     *
     * Skipped if the map already exists. Used to auto-trust legacy
     * type: ai-agent-skill packages on first upgrade — the user already
     * chose to require them.
     *
     * @param iterable<string> $packageNames
     */
    public function seedIfAbsent(iterable $packageNames): void
    {
        if ($this->configMapExists()) {
            return;
        }
        $rules = [];
        foreach ($packageNames as $name) {
            $rules[$name] = true;
        }
        if ($rules === []) {
            return;
        }
        $this->configRules = $rules;
        $this->persistFullMap($rules);
    }

    private function prompt(string $packageName): bool
    {
        $question = sprintf(
            'Package "<info>%s</info>" wants to register AI agent skills.' . PHP_EOL
            . 'Skills are instructions an AI agent will follow in your project.' . PHP_EOL
            . 'Allow this package to register skills?' . PHP_EOL
            . '  [<comment>y</comment>] Yes — allow & persist (writes to composer.json)' . PHP_EOL
            . '  [<comment>n</comment>] No — deny & persist (suppress future prompts)' . PHP_EOL
            . '  [<comment>a</comment>] Allow for this session only' . PHP_EOL
            . '  [<comment>d</comment>] Discard — do not allow, do not write' . PHP_EOL
            . '(defaults to <comment>n</comment>) [y,n,a,d]: ',
            $packageName,
        );

        $answer = $this->io->ask($question, 'n');
        if (!is_string($answer)) {
            $answer = 'n';
        }
        $decision = TrustDecision::fromAnswer($answer) ?? TrustDecision::Deny;

        if ($decision->isPersistent()) {
            $allow = $decision === TrustDecision::Allow;
            $rules = $this->loadConfigRules();
            $rules[$packageName] = $allow;
            $this->configRules = $rules;
            $this->persistFullMap($rules);
        } else {
            $this->sessionRules[$packageName] = $decision->grantsAccess();
        }

        return $decision->grantsAccess();
    }

    /**
     * Rewrite the allow-skills map without disturbing other extra.ai-agent-skill data.
     *
     * If the root project is itself a skill provider — i.e. its composer.json
     * declares extra.ai-agent-skill as a string or array of paths — those paths
     * are migrated under a `skills` sub-key so allow-skills can live alongside
     * them. Object-form configs are merged: existing keys are preserved and
     * `allow-skills` is set/replaced.
     *
     * @param array<string, bool> $rules
     */
    private function persistFullMap(array $rules): void
    {
        $path = $this->rootDir . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($path)) {
            return;
        }
        $contents = (string) file_get_contents($path);

        $merged = $this->mergeWithExisting($contents, $rules);

        $manipulator = new JsonManipulator($contents);
        $manipulator->addSubNode('extra', self::EXTRA_KEY, $merged);
        file_put_contents($path, $manipulator->getContents());
    }

    /**
     * Build the new extra.ai-agent-skill object, preserving any existing data.
     *
     * @param array<string, bool> $rules
     * @return array<string, mixed>
     */
    private function mergeWithExisting(string $composerJsonContents, array $rules): array
    {
        $data = json_decode($composerJsonContents, true);
        $existing = is_array($data) ? ($data['extra'][self::EXTRA_KEY] ?? null) : null;

        $merged = [];

        if (is_string($existing)) {
            // Legacy: "extra.ai-agent-skill": "skills/foo.md" — migrate to skills sub-key.
            $merged['skills'] = [$existing];
        } elseif (is_array($existing) && array_is_list($existing)) {
            // Legacy: array of paths.
            $merged['skills'] = $existing;
        } elseif (is_array($existing)) {
            // Object form: keep every existing sub-key (skills, etc.) and overlay allow-skills.
            foreach ($existing as $key => $value) {
                if (is_string($key)) {
                    $merged[$key] = $value;
                }
            }
        }

        $merged[self::ALLOW_SUB_KEY] = $rules;
        return $merged;
    }

    private function configMapExists(): bool
    {
        $path = $this->rootDir . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($path)) {
            return false;
        }
        $data = json_decode((string) file_get_contents($path), true);
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

    private function matchPattern(string $packageName): ?bool
    {
        foreach ($this->loadConfigRules() as $pattern => $allow) {
            if ($pattern === $packageName) {
                return $allow;
            }
            $regex = BasePackage::packageNameToRegexp($pattern);
            if (preg_match($regex, $packageName) === 1) {
                return $allow;
            }
        }
        return null;
    }

    /**
     * @return array<string, bool>
     */
    private function loadConfigRules(): array
    {
        if ($this->configRules !== null) {
            return $this->configRules;
        }
        $path = $this->rootDir . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($path)) {
            return $this->configRules = [];
        }
        $raw = (string) file_get_contents($path);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $this->configRules = [];
        }
        $extra = $data['extra'] ?? null;
        if (!is_array($extra)) {
            return $this->configRules = [];
        }
        $skillExtra = $extra[self::EXTRA_KEY] ?? null;
        if (!is_array($skillExtra)) {
            return $this->configRules = [];
        }
        $rules = $skillExtra[self::ALLOW_SUB_KEY] ?? [];
        if (!is_array($rules)) {
            return $this->configRules = [];
        }
        $clean = [];
        foreach ($rules as $pattern => $allow) {
            if (is_string($pattern) && is_bool($allow)) {
                $clean[$pattern] = $allow;
            }
        }
        return $this->configRules = $clean;
    }
}
