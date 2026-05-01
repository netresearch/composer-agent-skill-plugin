<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Trust;

use Composer\IO\IOInterface;

/**
 * Trust policy for skill packages.
 *
 * Three concerns: read decisions (delegated to {@see TrustStore}), prompt the
 * user when needed, and persist outcomes via the store. File I/O lives in the
 * store; this class is pure policy + IO orchestration.
 */
final class SkillTrustManager
{
    /** @var array<string, bool>|null Cached snapshot of the persistent rule map. */
    private ?array $configRules = null;

    /** @var array<string, bool> Session-only allow decisions (not persisted). */
    private array $sessionRules = [];

    public function __construct(
        private readonly IOInterface $io,
        private readonly TrustStore $store,
        private readonly ?string $rootPackageName = null,
    ) {
    }

    /**
     * Convenience factory: build a manager backed by a TrustStore at $composerJsonPath.
     *
     * Most callers want this — only tests that need a custom store implementation
     * should construct via the primary ctor.
     */
    public static function forComposerJson(IOInterface $io, string $composerJsonPath, ?string $rootPackageName = null): self
    {
        return new self($io, new TrustStore($composerJsonPath, $io), $rootPackageName);
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
     * Only call from the install/update boundary — never from informational
     * paths like `composer list-skills`. Mirrors Composer's PluginManager.
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
     * Explicitly persist a decision without prompting (used by `composer skills:trust`).
     */
    public function setExplicit(string $packageName, bool $allow): void
    {
        $rules = $this->loadConfigRules();
        $rules[$packageName] = $allow;
        $this->configRules = $rules;
        $this->store->saveAllowSkills($rules);
    }

    /**
     * Remove a package's persisted decision. Returns false if it wasn't there.
     */
    public function revoke(string $packageName): bool
    {
        $rules = $this->loadConfigRules();
        if (!array_key_exists($packageName, $rules)) {
            return false;
        }
        unset($rules[$packageName]);
        $this->configRules = $rules;
        $this->store->saveAllowSkills($rules);
        return true;
    }

    /**
     * Decide first-run trust policy for legacy `type: ai-agent-skill` packages.
     *
     * Runs only if the allow-skills map is absent and the input is non-empty.
     * See README "First-run policy".
     */
    public function applyFirstRunPolicy(FirstRunInput $input): void
    {
        if ($this->store->allowSkillsExists()) {
            return;
        }
        if ($input->isEmpty()) {
            return;
        }

        if (!$this->io->isInteractive()) {
            $this->io->writeError('<comment>The AI Agent Skill plugin found pre-existing skill packages but no trust decisions yet.</comment>');
            $this->io->writeError('<comment>Defaulting to deny-all in non-interactive mode. Authorize each one explicitly:</comment>');
            foreach ($input->legacyPackages as $pkg) {
                $marker = $input->isDirect($pkg) ? '(in your require)' : '(pulled in by another package)';
                $this->io->writeError(sprintf('  <info>composer skills:trust %s</info>  %s', $pkg, $marker));
            }
            $this->configRules = [];
            $this->store->saveAllowSkills([]);
            return;
        }

        $count = count($input->legacyPackages);
        $this->io->writeError(sprintf(
            '<info>The AI Agent Skill plugin found %d existing skill package%s that have not been authorized yet.</info>',
            $count,
            $count === 1 ? '' : 's',
        ));
        foreach ($input->legacyPackages as $pkg) {
            $marker = $input->isDirect($pkg)
                ? '<comment>(in your require)</comment>'
                : '<comment>(pulled in by another package)</comment>';
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
            'a' => array_fill_keys($input->legacyPackages, true),
            'd' => array_fill_keys($input->directOnly(), true),
            default => [],
        };
        $this->configRules = $rules;
        $this->store->saveAllowSkills($rules);
    }

    /**
     * Read-only access to the persisted rule map. Used by the list-trust command.
     *
     * @return array<string, bool>
     */
    public function getRules(): array
    {
        return $this->loadConfigRules();
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
            . '  [<comment>d</comment>] Discard — leave undecided, ask again next run' . PHP_EOL
            . '  [<comment>?</comment>] Show details about this choice' . PHP_EOL
            . '(change later with: composer skills:trust %s [--deny|--revoke])' . PHP_EOL
            . '(defaults to <comment>n</comment>) [y,n,a,d,?]: ',
            $packageName,
            $packageName,
        );

        // Help loop matching Composer's PluginManager prompt: `?` shows
        // descriptions and re-prompts.
        while (true) {
            $answer = $this->io->ask($question, 'n');
            if (!is_string($answer)) {
                $answer = 'n';
            }
            $choice = strtolower(trim($answer));
            if ($choice === '?') {
                $this->io->writeError([
                    'y - allow this package to register skills, write to composer.json',
                    'n - deny this package, write to composer.json (no future prompts)',
                    'a - allow for this install/update only, do not modify composer.json',
                    'd - skip without recording a decision; ask again next run',
                ]);
                continue;
            }
            $decision = TrustDecision::fromAnswer($choice) ?? TrustDecision::Deny;
            return match ($decision) {
                TrustDecision::Allow        => $this->persistAndReturn($packageName, true),
                TrustDecision::Deny         => $this->persistAndReturn($packageName, false),
                TrustDecision::SessionAllow => $this->sessionAllow($packageName),
                TrustDecision::Discard      => TrustState::Pending,
            };
        }
    }

    private function persistAndReturn(string $packageName, bool $allow): TrustState
    {
        $rules = $this->loadConfigRules();
        $rules[$packageName] = $allow;
        $this->configRules = $rules;
        $this->store->saveAllowSkills($rules);
        return $allow ? TrustState::Allowed : TrustState::Denied;
    }

    private function sessionAllow(string $packageName): TrustState
    {
        $this->sessionRules[$packageName] = true;
        return TrustState::Allowed;
    }

    private function matchPattern(string $packageName): ?bool
    {
        $rules = $this->loadConfigRules();

        // Two-pass: exact-string matches always win over glob patterns,
        // even when the glob was added first.
        if (array_key_exists($packageName, $rules)) {
            return $rules[$packageName];
        }
        foreach ($rules as $pattern => $allow) {
            if ($pattern === $packageName) {
                continue;
            }
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
     * Case-sensitive regex builder for trust patterns.
     *
     * Composer normalizes package names to lowercase, but
     * BasePackage::packageNameToRegexp always sets /i — case-insensitive
     * matching would let `acme/*: true` also trust `Acme/Evil` on private
     * repos that don't normalize. We use exact case here.
     */
    private static function patternToRegex(string $pattern): string
    {
        return '{^' . str_replace('\\*', '.*', preg_quote($pattern, '{')) . '$}';
    }

    /**
     * @return array<string, bool>
     */
    private function loadConfigRules(): array
    {
        if ($this->configRules !== null) {
            return $this->configRules;
        }
        return $this->configRules = $this->store->loadAllowSkills();
    }
}
