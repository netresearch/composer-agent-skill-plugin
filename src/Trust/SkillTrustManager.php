<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Trust;

use Composer\IO\IOInterface;
use Composer\Json\JsonManipulator;

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
        private readonly ?string $rootPackageName = null,
    ) {
    }

    public function hasDecision(string $packageName): bool
    {
        if ($packageName === $this->rootPackageName) {
            return true; // root project is implicitly self-trusted
        }
        if (isset($this->sessionRules[$packageName])) {
            return true;
        }
        return $this->matchPattern($packageName) !== null;
    }

    public function isAllowed(string $packageName): bool
    {
        if ($packageName === $this->rootPackageName) {
            return true;
        }
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
     *
     * Returns the resolved {@see TrustState} so callers don't need to re-query
     * hasDecision()/isAllowed() afterwards.
     */
    public function decide(string $packageName): TrustState
    {
        if ($this->hasDecision($packageName)) {
            return $this->isAllowed($packageName) ? TrustState::Allowed : TrustState::Denied;
        }

        if (!$this->io->isInteractive()) {
            $this->io->writeError(sprintf(
                '<warning>Skipping skills from "%s" — package is not in extra.ai-agent-skill.allow-skills.</warning>',
                $packageName,
            ));
            $this->io->writeError(sprintf(
                '  Allow: <info>composer skills:trust %s</info>',
                $packageName,
            ));
            $this->io->writeError(sprintf(
                '  Deny:  <info>composer skills:trust %s --deny</info>',
                $packageName,
            ));
            return TrustState::Pending;
        }

        return $this->prompt($packageName);
    }

    /**
     * Explicitly persist a decision without prompting.
     *
     * Used by the `composer skills:trust` command. Always writes to
     * composer.json regardless of prior state.
     */
    public function setExplicit(string $packageName, bool $allow): void
    {
        $rules = $this->loadConfigRules();
        $rules[$packageName] = $allow;
        $this->configRules = $rules;
        $this->persistFullMap($rules);
    }

    /**
     * Remove a package's persisted decision.
     *
     * If the package isn't in the map this is a no-op.
     */
    public function revoke(string $packageName): bool
    {
        $rules = $this->loadConfigRules();
        if (!array_key_exists($packageName, $rules)) {
            return false;
        }
        unset($rules[$packageName]);
        $this->configRules = $rules;
        $this->persistFullMap($rules);
        return true;
    }

    /**
     * Decide first-run trust policy for legacy `type: ai-agent-skill` packages.
     *
     * Runs only if the allow-skills map is absent and there are legacy packages
     * to consider. Three outcomes:
     *
     *   - **Interactive**: prompts `[n,d,a]` — None / Direct deps only / All.
     *     Default `n` (strict). The chosen subset is persisted.
     *   - **Non-interactive**: defaults to `n` and emits a warning listing the
     *     affected packages with `composer skills:trust ...` recovery commands.
     *
     * In every case the (possibly empty) map is written so this prompt fires
     * at most once per project.
     *
     * @param list<string> $legacyPackages    All installed packages with type:ai-agent-skill
     * @param list<string> $directDependencies Subset of $legacyPackages directly required by root composer.json
     */
    public function applyFirstRunPolicy(array $legacyPackages, array $directDependencies): void
    {
        if ($this->configMapExists()) {
            return;
        }
        if ($legacyPackages === []) {
            return;
        }

        $directSet = array_fill_keys($directDependencies, true);

        if (!$this->io->isInteractive()) {
            $this->io->writeError('<comment>The AI Agent Skill plugin found pre-existing skill packages but no trust decisions yet.</comment>');
            $this->io->writeError('<comment>Defaulting to deny-all in non-interactive mode. Authorize each one explicitly:</comment>');
            foreach ($legacyPackages as $pkg) {
                $marker = isset($directSet[$pkg]) ? '(direct)' : '(transitive)';
                $this->io->writeError(sprintf('  <info>composer skills:trust %s</info>  %s', $pkg, $marker));
            }
            $this->configRules = [];
            $this->persistFullMap([]);
            return;
        }

        $this->io->writeError(sprintf(
            '<info>The AI Agent Skill plugin found %d existing skill package%s that have not been authorized yet.</info>',
            count($legacyPackages),
            count($legacyPackages) === 1 ? '' : 's',
        ));
        foreach ($legacyPackages as $pkg) {
            $marker = isset($directSet[$pkg]) ? '<comment>(direct)</comment>' : '<comment>(transitive)</comment>';
            $this->io->writeError(sprintf('  - %s  %s', $pkg, $marker));
        }
        $question = 'How should they be trusted on this first run?' . PHP_EOL
            . '  [<comment>n</comment>] None — prompt for each package later (default, strict)' . PHP_EOL
            . '  [<comment>d</comment>] Direct dependencies only — auto-trust packages your root composer.json explicitly requires' . PHP_EOL
            . '  [<comment>a</comment>] All — auto-trust every existing skill package (including transitive)' . PHP_EOL
            . '(defaults to <comment>n</comment>) [n,d,a]: ';

        $answer = $this->io->ask($question, 'n');
        if (!is_string($answer)) {
            $answer = 'n';
        }
        $choice = strtolower(trim($answer));

        $rules = match ($choice) {
            'a' => array_fill_keys($legacyPackages, true),
            'd' => array_fill_keys(array_values(array_filter($legacyPackages, static fn (string $p): bool => isset($directSet[$p]))), true),
            default => [],
        };
        $this->configRules = $rules;
        $this->persistFullMap($rules);
    }

    private function prompt(string $packageName): TrustState
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

        return match ($decision) {
            TrustDecision::Allow => $this->persistAndReturn($packageName, true),
            TrustDecision::Deny  => $this->persistAndReturn($packageName, false),
            TrustDecision::SessionAllow => $this->sessionAllow($packageName),
            // Discard: no persist, no session entry — package stays pending and
            // will re-prompt next run.
            TrustDecision::Discard => TrustState::Pending,
        };
    }

    private function persistAndReturn(string $packageName, bool $allow): TrustState
    {
        $rules = $this->loadConfigRules();
        $rules[$packageName] = $allow;
        $this->configRules = $rules;
        $this->persistFullMap($rules);
        return $allow ? TrustState::Allowed : TrustState::Denied;
    }

    private function sessionAllow(string $packageName): TrustState
    {
        $this->sessionRules[$packageName] = true;
        return TrustState::Allowed;
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
        $newContents = $manipulator->getContents();

        // Write atomically: write to a sibling temp file then rename. Avoids leaving
        // a partially-written composer.json if the process is killed mid-write and
        // narrows the TOCTOU window with parallel composer.json writers.
        $tempPath = $path . '.skill-trust.' . bin2hex(random_bytes(8));
        if (@file_put_contents($tempPath, $newContents) === false) {
            $this->io->writeError(sprintf(
                '<error>Failed to write trust decisions to %s. Check file permissions.</error>',
                $path,
            ));
            return;
        }
        if (!@rename($tempPath, $path)) {
            @unlink($tempPath);
            $this->io->writeError(sprintf(
                '<error>Failed to atomically replace %s with trust decisions.</error>',
                $path,
            ));
        }
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
        $existing = null;
        if (is_array($data)) {
            $extra = $data['extra'] ?? null;
            if (is_array($extra)) {
                $existing = $extra[self::EXTRA_KEY] ?? null;
            }
        }

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

    /**
     * Convert a trust-map pattern to a case-sensitive regex.
     *
     * Composer package names are normalized to lowercase, so case-insensitive
     * matching (which Composer's BasePackage::packageNameToRegexp produces) would
     * let `acme/*: true` also trust `Acme/Evil` on private repos that don't
     * normalize. Trust matching uses exact case to avoid that surprise.
     */
    private static function patternToRegex(string $pattern): string
    {
        return '{^' . str_replace('\\*', '.*', preg_quote($pattern, '{')) . '$}';
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
        $rules = $this->loadConfigRules();

        // Two-pass: exact-string matches always win over glob patterns, even
        // when the glob was added first. Otherwise a user who runs
        //   composer skills:trust vendor/foo --deny
        // after an earlier `vendor/*: true` glob would be silently overruled.
        if (array_key_exists($packageName, $rules)) {
            return $rules[$packageName];
        }
        foreach ($rules as $pattern => $allow) {
            if ($pattern === $packageName) {
                continue; // already handled by the exact-match pass
            }
            // Skip non-glob patterns — they couldn't match anyway and skipping
            // here lets us avoid an extra preg_match per non-glob rule.
            if (!str_contains($pattern, '*')) {
                continue;
            }
            $regex = self::patternToRegex($pattern);
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
