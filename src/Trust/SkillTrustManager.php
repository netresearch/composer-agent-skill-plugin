<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Trust;

use Composer\IO\IOInterface;
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
        $rules = $data['extra'][self::EXTRA_KEY][self::ALLOW_SUB_KEY] ?? [];
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
