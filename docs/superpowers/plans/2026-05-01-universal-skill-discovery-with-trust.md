# Universal Skill Discovery with Trust Prompt — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow any Composer package (regardless of `type`) to ship AI agent skills via `extra.ai-agent-skill`, gated by a per-package trust prompt modeled on Composer's own `allow-plugins` flow. Closes [#42](https://github.com/netresearch/composer-agent-skill-plugin/issues/42).

**Architecture:**
1. `SkillDiscovery` stops filtering by package `type`. Discovery iterates **all** installed packages and selects those declaring `extra.ai-agent-skill` OR carrying the legacy `type: ai-agent-skill`.
2. **Discovery is pure** — `discoverAllSkills()` returns every declared skill annotated with a `trust_state` (`allowed` / `denied` / `pending`). It never prompts. The gating call lives at the install/update boundary inside `SkillPlugin::updateAgentsMd()`, which calls `SkillTrustManager::decide()` (the only method that may prompt). `composer list-skills` uses pure discovery + reads trust state for display, so listing skills never triggers a prompt.
3. A new `SkillTrustManager` consults `extra.ai-agent-skill.allow-skills` in the **root** `composer.json`. Unknown packages trigger an interactive prompt (`y` / `n` / `a` / `d`) like Composer's `PluginManager`. Decisions persist by rewriting the full `allow-skills` map atomically (avoids ambiguity around dot-paths with slashes inside `JsonManipulator::addSubNode`). Non-interactive runs skip untrusted packages with a hint instead of hard-failing.
4. **Auto-seed on first run**: if `extra.ai-agent-skill.allow-skills` is absent, every currently-installed `type: ai-agent-skill` package is seeded as `true` (the user already chose to `composer require` them — implicit trust). Pre-upgrade users don't get re-prompted on every legacy skill package.
5. A `PackageProvider` abstraction wraps `InstalledVersions` so unit tests can inject fixtures.

**Tech Stack:** PHP 8.4, `composer-plugin-api ^2.1`, `symfony/console`, `symfony/yaml`, PHPUnit 13, PHPStan level 8.

**References:**
- [Composer `PluginManager::isPluginAllowed()`](https://github.com/composer/composer/blob/main/src/Composer/Plugin/PluginManager.php) — prompt shape, persistence
- [`Composer\Json\JsonManipulator`](https://github.com/composer/composer/blob/main/src/Composer/Json/JsonManipulator.php) — preserves formatting on writes
- [Symfony Flex contrib recipe prompt](https://github.com/symfony/flex/blob/2.x/src/Flex.php) — `[a]` session-only and `[p]` permanent options
- [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer) — sidecar discovery model (no trust)

---

## File Structure

**New files:**
- `src/Package/PackageInfo.php` — DTO with `name`, `installPath`, `version`, `type`, `extra` (array)
- `src/Package/PackageProvider.php` — interface: `iterAllPackages(): iterable<PackageInfo>`
- `src/Package/InstalledVersionsProvider.php` — default impl wrapping `InstalledVersions`
- `src/Trust/SkillTrustManager.php` — trust decisions (allow / deny / prompt / persist / glob)
- `src/Trust/TrustDecision.php` — enum: `Allow`, `Deny`, `Discard`, `SessionAllow`
- `tests/Fixtures/library-with-skill/composer.json` — `type: library` + `extra.ai-agent-skill`
- `tests/Fixtures/library-with-skill/SKILL.md`
- `tests/Unit/Package/InstalledVersionsProviderTest.php`
- `tests/Unit/Trust/SkillTrustManagerTest.php`

**Modified files:**
- `src/SkillDiscovery.php` — accept `PackageProvider` + `SkillTrustManager` in constructor; iterate all packages; consult trust before reading skills
- `src/SkillPlugin.php` — wire up `SkillTrustManager` in `updateAgentsMd()`
- `src/Commands/ListSkillsCommand.php` — add `[allowed]` / `[denied]` / `[pending]` status column
- `tests/Unit/SkillDiscoveryTest.php` — use `PackageProvider` fixtures instead of static `InstalledVersions`
- `README.md` — new "Trust Model" section, library-bundled-skills example
- `CHANGELOG.md` — entry under Unreleased
- `docs/PRD.md` — append "Universal Discovery & Trust" addendum

---

## Task 1: Add `PackageInfo` value object

**Files:**
- Create: `src/Package/PackageInfo.php`
- Test: `tests/Unit/Package/PackageInfoTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Package;

use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use PHPUnit\Framework\TestCase;

final class PackageInfoTest extends TestCase
{
    public function testHoldsAllFields(): void
    {
        $info = new PackageInfo(
            name: 'vendor/foo',
            installPath: '/tmp/vendor/foo',
            version: '1.2.3',
            type: 'library',
            extra: ['ai-agent-skill' => 'SKILL.md'],
        );

        self::assertSame('vendor/foo', $info->name);
        self::assertSame('/tmp/vendor/foo', $info->installPath);
        self::assertSame('1.2.3', $info->version);
        self::assertSame('library', $info->type);
        self::assertSame(['ai-agent-skill' => 'SKILL.md'], $info->extra);
    }

    public function testDeclaresSkillsWhenExtraKeyPresent(): void
    {
        $info = new PackageInfo('a/b', '/p', '1.0.0', 'library', ['ai-agent-skill' => 'SKILL.md']);
        self::assertTrue($info->declaresSkills());
    }

    public function testDeclaresSkillsForLegacyType(): void
    {
        $info = new PackageInfo('a/b', '/p', '1.0.0', 'ai-agent-skill', []);
        self::assertTrue($info->declaresSkills());
    }

    public function testDoesNotDeclareSkillsOtherwise(): void
    {
        $info = new PackageInfo('a/b', '/p', '1.0.0', 'library', []);
        self::assertFalse($info->declaresSkills());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/Package/PackageInfoTest.php
```
Expected: FAIL with `Class … not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php
declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Package;

/**
 * @phpstan-type ExtraData array<string, mixed>
 */
final readonly class PackageInfo
{
    /**
     * @param ExtraData $extra
     */
    public function __construct(
        public string $name,
        public string $installPath,
        public string $version,
        public string $type,
        public array $extra,
    ) {
    }

    public function declaresSkills(): bool
    {
        return $this->type === 'ai-agent-skill' || isset($this->extra['ai-agent-skill']);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/Package/PackageInfoTest.php
```
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Package/PackageInfo.php tests/Unit/Package/PackageInfoTest.php
git commit -S --signoff -m "feat: add PackageInfo DTO for skill discovery"
```

---

## Task 2: Add `PackageProvider` interface and default impl

**Files:**
- Create: `src/Package/PackageProvider.php`
- Create: `src/Package/InstalledVersionsProvider.php`
- Test: `tests/Unit/Package/InstalledVersionsProviderTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Package;

use Netresearch\ComposerAgentSkillPlugin\Package\InstalledVersionsProvider;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider;
use PHPUnit\Framework\TestCase;

final class InstalledVersionsProviderTest extends TestCase
{
    public function testImplementsContract(): void
    {
        self::assertInstanceOf(PackageProvider::class, new InstalledVersionsProvider());
    }

    public function testYieldsCurrentRootPackageAtMinimum(): void
    {
        $provider = new InstalledVersionsProvider();
        $packages = iterator_to_array($provider->iterAllPackages(), false);

        self::assertNotEmpty($packages);
        foreach ($packages as $pkg) {
            self::assertInstanceOf(PackageInfo::class, $pkg);
            self::assertNotSame('', $pkg->name);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/Package/InstalledVersionsProviderTest.php
```
Expected: FAIL with class-not-found.

- [ ] **Step 3: Write minimal implementations**

`src/Package/PackageProvider.php`:
```php
<?php
declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Package;

interface PackageProvider
{
    /**
     * @return iterable<PackageInfo>
     */
    public function iterAllPackages(): iterable;
}
```

`src/Package/InstalledVersionsProvider.php`:
```php
<?php
declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Package;

use Composer\InstalledVersions;

final class InstalledVersionsProvider implements PackageProvider
{
    public function iterAllPackages(): iterable
    {
        foreach (InstalledVersions::getInstalledPackages() as $name) {
            $installPath = InstalledVersions::getInstallPath($name);
            $version = InstalledVersions::getPrettyVersion($name);

            if ($installPath === null || $version === null) {
                continue;
            }

            $composerJson = $installPath . DIRECTORY_SEPARATOR . 'composer.json';
            $type = 'library';
            $extra = [];

            if (file_exists($composerJson)) {
                $data = json_decode((string) file_get_contents($composerJson), true);
                if (is_array($data)) {
                    $type = is_string($data['type'] ?? null) ? $data['type'] : 'library';
                    $extra = is_array($data['extra'] ?? null) ? $data['extra'] : [];
                }
            }

            yield new PackageInfo($name, $installPath, $version, $type, $extra);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/Package/InstalledVersionsProviderTest.php
```
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Package/PackageProvider.php src/Package/InstalledVersionsProvider.php tests/Unit/Package/InstalledVersionsProviderTest.php
git commit -S --signoff -m "feat: add PackageProvider abstraction over InstalledVersions"
```

---

## Task 3: Add fixture for library-bundled skill

**Files:**
- Create: `tests/Fixtures/library-with-skill/composer.json`
- Create: `tests/Fixtures/library-with-skill/SKILL.md`

- [ ] **Step 1: Create fixture composer.json**

```json
{
    "name": "test/library-with-skill",
    "description": "Test fixture: a regular library that ships a skill",
    "type": "library",
    "require": {
        "php": "^8.2"
    },
    "extra": {
        "ai-agent-skill": "SKILL.md"
    }
}
```

- [ ] **Step 2: Create fixture SKILL.md**

```markdown
---
name: library-bundled-skill
description: Skill shipped by a regular library package, not a dedicated skill package
---

# Library Bundled Skill

Used to verify universal discovery picks up libraries declaring extra.ai-agent-skill
even when their type is "library" (not "ai-agent-skill").
```

- [ ] **Step 3: Verify fixture parses cleanly**

```bash
php -r 'var_dump(json_decode(file_get_contents("tests/Fixtures/library-with-skill/composer.json"), true));' | head -20
```
Expected: array with `type=>library` and `extra.ai-agent-skill=>SKILL.md`.

- [ ] **Step 4: Commit**

```bash
git add tests/Fixtures/library-with-skill/
git commit -S --signoff -m "test: add fixture for library that bundles a skill"
```

---

## Task 4: Refactor `SkillDiscovery` to accept `PackageProvider`

**Files:**
- Modify: `src/SkillDiscovery.php` (constructor + `discoverAllSkills`)
- Modify: `tests/Unit/SkillDiscoveryTest.php` (tests already use it via reflection — no behavior change here)

This task is a pure refactor. No new behavior. Wires the abstraction in without changing what's discovered (still filters by `type`).

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/SkillDiscoveryTest.php`:

```php
public function testAcceptsInjectedPackageProvider(): void
{
    $provider = new \Netresearch\ComposerAgentSkillPlugin\Package\InstalledVersionsProvider();
    $discovery = new SkillDiscovery($this->io, $provider);
    self::assertInstanceOf(SkillDiscovery::class, $discovery);
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit --filter testAcceptsInjectedPackageProvider tests/Unit/SkillDiscoveryTest.php
```
Expected: FAIL — constructor only takes `IOInterface`.

- [ ] **Step 3: Modify `SkillDiscovery` constructor**

Replace lines 27–30 of `src/SkillDiscovery.php`:

```php
public function __construct(
    private readonly IOInterface $io,
    private readonly ?\Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider $packages = null,
) {
}
```

Replace `discoverAllSkills()` body to use the provider when present (still type-filtered until Task 5):

```php
public function discoverAllSkills(): array
{
    $this->warnings = [];
    $skills = [];
    $skillNames = [];

    $iter = $this->packages !== null
        ? $this->packages->iterAllPackages()
        : $this->iterByType();

    foreach ($iter as $pkg) {
        if ($pkg->type !== self::TYPE_AI_AGENT_SKILL) {
            continue; // Task 5 broadens this
        }

        $packageSkills = $this->discoverSkillsFromPackage($pkg->name, $pkg->installPath, $pkg->version);

        foreach ($packageSkills as $skill) {
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
    ksort($skills);
    return array_values($skills);
}

/**
 * @return iterable<\Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo>
 */
private function iterByType(): iterable
{
    foreach (\Composer\InstalledVersions::getInstalledPackagesByType(self::TYPE_AI_AGENT_SKILL) as $name) {
        $path = \Composer\InstalledVersions::getInstallPath($name);
        $version = \Composer\InstalledVersions::getPrettyVersion($name);
        if ($path === null || $version === null) {
            continue;
        }
        yield new \Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo(
            name: $name, installPath: $path, version: $version, type: self::TYPE_AI_AGENT_SKILL, extra: []
        );
    }
}
```

- [ ] **Step 4: Run full suite**

```bash
vendor/bin/phpunit
```
Expected: PASS — all 32 tests including the new one.

- [ ] **Step 5: Commit**

```bash
git add src/SkillDiscovery.php tests/Unit/SkillDiscoveryTest.php
git commit -S --signoff -m "refactor: thread PackageProvider through SkillDiscovery"
```

---

## Task 5: Broaden discovery to library-bundled skills

**Files:**
- Modify: `src/SkillDiscovery.php` (drop type filter)
- Test: `tests/Unit/SkillDiscoveryTest.php`

- [ ] **Step 1: Write the failing test**

Add a stub provider helper at top of `tests/Unit/SkillDiscoveryTest.php` (after `use` statements):

```php
use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider;
```

Then add the test:

```php
public function testDiscoversLibraryBundledSkill(): void
{
    $fixturePath = realpath(__DIR__ . '/../Fixtures/library-with-skill');
    self::assertNotFalse($fixturePath);

    $provider = new class ($fixturePath) implements PackageProvider {
        public function __construct(private string $path) {}
        public function iterAllPackages(): iterable
        {
            yield new PackageInfo(
                name: 'test/library-with-skill',
                installPath: $this->path,
                version: '1.0.0',
                type: 'library',
                extra: ['ai-agent-skill' => 'SKILL.md'],
            );
        }
    };

    $discovery = new SkillDiscovery($this->io, $provider);
    $skills = $discovery->discoverAllSkills();

    self::assertCount(1, $skills);
    self::assertSame('library-bundled-skill', $skills[0]['name']);
    self::assertSame('test/library-with-skill', $skills[0]['package']);
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit --filter testDiscoversLibraryBundledSkill
```
Expected: FAIL — `discoverAllSkills` returns empty (type filter still active).

- [ ] **Step 3: Drop the type filter**

In `src/SkillDiscovery.php`, replace the loop guard:

```php
foreach ($iter as $pkg) {
    if (!$pkg->declaresSkills()) {
        continue;
    }
    // ... rest unchanged
}
```

- [ ] **Step 4: Run full suite**

```bash
vendor/bin/phpunit
```
Expected: PASS — all tests including the new library-bundled one.

- [ ] **Step 5: Commit**

```bash
git add src/SkillDiscovery.php tests/Unit/SkillDiscoveryTest.php
git commit -S --signoff -m "feat: discover skills in any package via extra.ai-agent-skill"
```

---

## Task 6: Add `TrustDecision` enum

**Files:**
- Create: `src/Trust/TrustDecision.php`
- Test: `tests/Unit/Trust/TrustDecisionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Trust;

use Netresearch\ComposerAgentSkillPlugin\Trust\TrustDecision;
use PHPUnit\Framework\TestCase;

final class TrustDecisionTest extends TestCase
{
    public function testFromAnswerY(): void
    {
        self::assertSame(TrustDecision::Allow, TrustDecision::fromAnswer('y'));
    }

    public function testFromAnswerN(): void
    {
        self::assertSame(TrustDecision::Deny, TrustDecision::fromAnswer('n'));
    }

    public function testFromAnswerASessionAllow(): void
    {
        self::assertSame(TrustDecision::SessionAllow, TrustDecision::fromAnswer('a'));
    }

    public function testFromAnswerD(): void
    {
        self::assertSame(TrustDecision::Discard, TrustDecision::fromAnswer('d'));
    }

    public function testFromAnswerInvalidReturnsNull(): void
    {
        self::assertNull(TrustDecision::fromAnswer('x'));
    }

    public function testIsPersistent(): void
    {
        self::assertTrue(TrustDecision::Allow->isPersistent());
        self::assertTrue(TrustDecision::Deny->isPersistent());
        self::assertFalse(TrustDecision::SessionAllow->isPersistent());
        self::assertFalse(TrustDecision::Discard->isPersistent());
    }

    public function testGrantsAccess(): void
    {
        self::assertTrue(TrustDecision::Allow->grantsAccess());
        self::assertTrue(TrustDecision::SessionAllow->grantsAccess());
        self::assertFalse(TrustDecision::Deny->grantsAccess());
        self::assertFalse(TrustDecision::Discard->grantsAccess());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/Trust/TrustDecisionTest.php
```
Expected: FAIL — enum does not exist.

- [ ] **Step 3: Write the enum**

```php
<?php
declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Trust;

enum TrustDecision: string
{
    case Allow        = 'allow';
    case Deny         = 'deny';
    case SessionAllow = 'session-allow';
    case Discard      = 'discard';

    public static function fromAnswer(string $answer): ?self
    {
        return match (strtolower(trim($answer))) {
            'y' => self::Allow,
            'n' => self::Deny,
            'a' => self::SessionAllow,
            'd' => self::Discard,
            default => null,
        };
    }

    public function isPersistent(): bool
    {
        return $this === self::Allow || $this === self::Deny;
    }

    public function grantsAccess(): bool
    {
        return $this === self::Allow || $this === self::SessionAllow;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/Trust/TrustDecisionTest.php
```
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Trust/TrustDecision.php tests/Unit/Trust/TrustDecisionTest.php
git commit -S --signoff -m "feat: add TrustDecision enum (allow/deny/session/discard)"
```

---

## Task 7: Build `SkillTrustManager` — read existing config

**Files:**
- Create: `src/Trust/SkillTrustManager.php`
- Test: `tests/Unit/Trust/SkillTrustManagerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Trust;

use Composer\IO\BufferIO;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use PHPUnit\Framework\TestCase;

final class SkillTrustManagerTest extends TestCase
{
    private string $rootDir;
    private string $composerJsonPath;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/skill-trust-' . uniqid();
        mkdir($this->rootDir);
        $this->composerJsonPath = $this->rootDir . '/composer.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->composerJsonPath)) {
            unlink($this->composerJsonPath);
        }
        if (is_dir($this->rootDir)) {
            rmdir($this->rootDir);
        }
    }

    public function testReadsExplicitAllow(): void
    {
        file_put_contents($this->composerJsonPath, json_encode([
            'extra' => [
                'ai-agent-skill' => [
                    'allow-skills' => ['vendor/foo' => true],
                ],
            ],
        ]));
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
        self::assertTrue($mgr->isAllowed('vendor/foo'));
    }

    public function testReadsExplicitDeny(): void
    {
        file_put_contents($this->composerJsonPath, json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => false]]],
        ]));
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
        self::assertFalse($mgr->isAllowed('vendor/foo'));
        self::assertTrue($mgr->hasDecision('vendor/foo'));
    }

    public function testGlobMatch(): void
    {
        file_put_contents($this->composerJsonPath, json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['trusted-org/*' => true]]],
        ]));
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
        self::assertTrue($mgr->isAllowed('trusted-org/some-skill'));
        self::assertFalse($mgr->hasDecision('other/pkg'));
    }

    public function testNoConfigMeansNoDecision(): void
    {
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
        self::assertFalse($mgr->hasDecision('vendor/foo'));
        self::assertFalse($mgr->isAllowed('vendor/foo'));
    }

    public function testMissingComposerJsonIsHandled(): void
    {
        $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
        self::assertFalse($mgr->hasDecision('any/pkg'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/Trust/SkillTrustManagerTest.php
```
Expected: FAIL — class missing.

- [ ] **Step 3: Implement read-only manager (decide() comes in Task 8)**

```php
<?php
declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Trust;

use Composer\IO\IOInterface;
use Composer\Package\BasePackage;

final class SkillTrustManager
{
    private const EXTRA_KEY      = 'ai-agent-skill';
    private const ALLOW_SUB_KEY  = 'allow-skills';

    /** @var array<string, bool>|null Cache of root config entries. */
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
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/Trust/SkillTrustManagerTest.php
```
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Trust/SkillTrustManager.php tests/Unit/Trust/SkillTrustManagerTest.php
git commit -S --signoff -m "feat: add SkillTrustManager with allow-skills config + glob"
```

---

## Task 8: `SkillTrustManager::decide()` — prompt + persist

**Files:**
- Modify: `src/Trust/SkillTrustManager.php`
- Modify: `tests/Unit/Trust/SkillTrustManagerTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/Trust/SkillTrustManagerTest.php`:

```php
public function testDecideRespectsExistingAllow(): void
{
    file_put_contents($this->composerJsonPath, json_encode([
        'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => true]]],
    ]));
    $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
    self::assertTrue($mgr->decide('vendor/foo'));
}

public function testDecideNonInteractiveSkipsAndWarns(): void
{
    $io = new BufferIO();
    $mgr = new SkillTrustManager($io, $this->rootDir);
    self::assertFalse($mgr->decide('vendor/new'));
    self::assertStringContainsString('vendor/new', $io->getOutput());
    self::assertStringContainsString('composer config', $io->getOutput());
}

public function testDecideInteractiveAllowPersistsToComposerJson(): void
{
    file_put_contents($this->composerJsonPath, "{\n}\n");
    $io = new BufferIO("y\n");
    $io->setInteractive(true);
    $mgr = new SkillTrustManager($io, $this->rootDir);

    self::assertTrue($mgr->decide('vendor/foo'));

    $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
    self::assertSame(true, $data['extra']['ai-agent-skill']['allow-skills']['vendor/foo']);
}

public function testDecideInteractiveDenyPersistsAsFalse(): void
{
    file_put_contents($this->composerJsonPath, "{\n}\n");
    $io = new BufferIO("n\n");
    $io->setInteractive(true);
    $mgr = new SkillTrustManager($io, $this->rootDir);

    self::assertFalse($mgr->decide('vendor/bar'));

    $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
    self::assertSame(false, $data['extra']['ai-agent-skill']['allow-skills']['vendor/bar']);
}

public function testDecideInteractiveSessionAllowDoesNotWriteFile(): void
{
    $original = "{\n}\n";
    file_put_contents($this->composerJsonPath, $original);
    $io = new BufferIO("a\n");
    $io->setInteractive(true);
    $mgr = new SkillTrustManager($io, $this->rootDir);

    self::assertTrue($mgr->decide('vendor/baz'));
    self::assertSame($original, file_get_contents($this->composerJsonPath));
    self::assertTrue($mgr->isAllowed('vendor/baz')); // session cache
}

public function testDecideInteractiveDiscardDoesNotWriteOrAllow(): void
{
    $original = "{\n}\n";
    file_put_contents($this->composerJsonPath, $original);
    $io = new BufferIO("d\n");
    $io->setInteractive(true);
    $mgr = new SkillTrustManager($io, $this->rootDir);

    self::assertFalse($mgr->decide('vendor/qux'));
    self::assertSame($original, file_get_contents($this->composerJsonPath));
    self::assertFalse($mgr->isAllowed('vendor/qux'));
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit tests/Unit/Trust/SkillTrustManagerTest.php
```
Expected: 6 new failures — `decide()` doesn't exist.

- [ ] **Step 3: Add `decide()` and persistence helpers**

Persistence rewrites the **full `allow-skills` map** in one go (instead of trying to address `extra.ai-agent-skill.allow-skills.vendor/foo` via a dot-path — the slash-in-leaf-key is past what `JsonManipulator::addSubNode` exercises in the wild). The root project never declares `extra.ai-agent-skill` as a path string/array (it's a consumer, not a skill bundler), so clobbering the whole sub-tree is safe.

Append to `src/Trust/SkillTrustManager.php` (inside the class):

```php
public function decide(string $packageName): bool
{
    if ($this->hasDecision($packageName)) {
        return $this->isAllowed($packageName);
    }

    if (!$this->io->isInteractive()) {
        $this->io->writeError(sprintf(
            '<warning>Skipping skills from "%s" — package is not in extra.ai-agent-skill.allow-skills.</warning>',
            $packageName
        ));
        $this->io->writeError(sprintf(
            '  Run: <info>composer config --json extra.ai-agent-skill.allow-skills \'{"%s": true}\'</info>',
            $packageName
        ));
        return false;
    }

    return $this->prompt($packageName);
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
        . '(defaults to <comment>n</comment>) [y,n,a,d]: '
    );

    $answer = $this->io->ask($question, 'n');
    if (!is_string($answer)) {
        $answer = 'n';
    }
    $decision = TrustDecision::fromAnswer($answer) ?? TrustDecision::Deny;

    if ($decision->isPersistent()) {
        $allow = $decision === TrustDecision::Allow;
        // Update in-memory cache first, then persist the whole map.
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
 * Rewrite the entire allow-skills map atomically.
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
    $manipulator = new \Composer\Json\JsonManipulator($contents);
    // Two-level write: extra.ai-agent-skill = { allow-skills: {...} }
    $manipulator->addSubNode('extra', self::EXTRA_KEY, [self::ALLOW_SUB_KEY => $rules]);
    file_put_contents($path, $manipulator->getContents());
}

/**
 * Seed the allow-skills map with currently-installed packages.
 *
 * Called on first encounter (when the map is absent). Pre-existing
 * type: ai-agent-skill packages get auto-trusted because the user already
 * chose to `composer require` them — implicit trust.
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
    return isset($data['extra'][self::EXTRA_KEY][self::ALLOW_SUB_KEY])
        && is_array($data['extra'][self::EXTRA_KEY][self::ALLOW_SUB_KEY]);
}
```

Add a corresponding test for `seedIfAbsent()` to `SkillTrustManagerTest`:

```php
public function testSeedIfAbsentWritesMap(): void
{
    file_put_contents($this->composerJsonPath, "{\n}\n");
    $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
    $mgr->seedIfAbsent(['vendor/legacy-a', 'vendor/legacy-b']);

    $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
    self::assertSame(true, $data['extra']['ai-agent-skill']['allow-skills']['vendor/legacy-a']);
    self::assertSame(true, $data['extra']['ai-agent-skill']['allow-skills']['vendor/legacy-b']);
}

public function testSeedIfAbsentSkipsWhenMapPresent(): void
{
    file_put_contents($this->composerJsonPath, json_encode([
        'extra' => ['ai-agent-skill' => ['allow-skills' => ['vendor/foo' => true]]],
    ], JSON_PRETTY_PRINT));
    $original = (string) file_get_contents($this->composerJsonPath);

    $mgr = new SkillTrustManager(new BufferIO(), $this->rootDir);
    $mgr->seedIfAbsent(['vendor/should-not-appear']);

    self::assertSame($original, file_get_contents($this->composerJsonPath));
}
```

And update `testDecideInteractiveAllowPersistsToComposerJson` to assert against the round-trip (full map):

```php
public function testDecideInteractiveAllowPersistsToComposerJson(): void
{
    file_put_contents($this->composerJsonPath, "{\n}\n");
    $io = new BufferIO("y\n");
    $io->setInteractive(true);
    $mgr = new SkillTrustManager($io, $this->rootDir);

    self::assertTrue($mgr->decide('vendor/foo'));

    $data = json_decode((string) file_get_contents($this->composerJsonPath), true);
    self::assertSame(['vendor/foo' => true], $data['extra']['ai-agent-skill']['allow-skills']);
}
```

- [ ] **Step 4: Run all tests**

```bash
vendor/bin/phpunit
```
Expected: PASS — all tests including the 6 new ones.

- [ ] **Step 5: Commit**

```bash
git add src/Trust/SkillTrustManager.php tests/Unit/Trust/SkillTrustManagerTest.php
git commit -S --signoff -m "feat: SkillTrustManager prompts user and persists decisions"
```

---

## Task 9: Annotate skills with `trust_state`, gate `AGENTS.md` at the install boundary

**Critical design point:** `SkillDiscovery::discoverAllSkills()` stays **pure** — it never prompts. It just enumerates declared skills and tags each with a `trust_state` (`allowed` / `denied` / `pending`). The actual prompt/gate runs from `SkillPlugin::updateAgentsMd()` only — the install/update boundary, mirroring how Composer keeps `allow-plugins` prompts at activation. This way `composer list-skills` (Task 10) can show a full inventory without firing prompts.

**Files:**
- Modify: `src/SkillDiscovery.php` (add `trust_state`; do NOT call `decide()`)
- Modify: `src/SkillPlugin.php` (seed → discover → filter via `decide()` → generate)
- Test: `tests/Unit/SkillDiscoveryTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/SkillDiscoveryTest.php`:

```php
public function testTrustStateAnnotatedAllowed(): void
{
    $fixturePath = realpath(__DIR__ . '/../Fixtures/library-with-skill');
    self::assertNotFalse($fixturePath);

    $provider = new class ($fixturePath) implements PackageProvider {
        public function __construct(private string $path) {}
        public function iterAllPackages(): iterable
        {
            yield new PackageInfo(
                name: 'allowed/pkg',
                installPath: $this->path,
                version: '1.0.0',
                type: 'library',
                extra: ['ai-agent-skill' => 'SKILL.md'],
            );
        }
    };

    $rootDir = sys_get_temp_dir() . '/discovery-trust-' . uniqid();
    mkdir($rootDir);
    file_put_contents($rootDir . '/composer.json', json_encode([
        'extra' => ['ai-agent-skill' => ['allow-skills' => ['allowed/pkg' => true]]],
    ]));

    try {
        $trust = new \Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager(
            new \Composer\IO\BufferIO(), $rootDir
        );
        $discovery = new SkillDiscovery($this->io, $provider, $trust);
        $skills = $discovery->discoverAllSkills();

        self::assertCount(1, $skills);
        self::assertSame('allowed', $skills[0]['trust_state']);
    } finally {
        unlink($rootDir . '/composer.json');
        rmdir($rootDir);
    }
}

public function testTrustStateAnnotatedPendingWithoutPrompt(): void
{
    // Even non-interactive IO must NOT cause discoverAllSkills to skip the entry.
    // Discovery is pure; the install boundary handles gating.
    $fixturePath = realpath(__DIR__ . '/../Fixtures/library-with-skill');
    self::assertNotFalse($fixturePath);

    $provider = new class ($fixturePath) implements PackageProvider {
        public function __construct(private string $path) {}
        public function iterAllPackages(): iterable
        {
            yield new PackageInfo(
                name: 'unknown/pkg',
                installPath: $this->path,
                version: '1.0.0',
                type: 'library',
                extra: ['ai-agent-skill' => 'SKILL.md'],
            );
        }
    };

    $rootDir = sys_get_temp_dir() . '/discovery-pending-' . uniqid();
    mkdir($rootDir);
    file_put_contents($rootDir . '/composer.json', "{\n}\n");

    try {
        $trust = new \Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager(
            new \Composer\IO\BufferIO(), $rootDir // non-interactive by default
        );
        $discovery = new SkillDiscovery($this->io, $provider, $trust);
        $skills = $discovery->discoverAllSkills();

        self::assertCount(1, $skills, 'Discovery must include pending skills, not skip them');
        self::assertSame('pending', $skills[0]['trust_state']);
    } finally {
        unlink($rootDir . '/composer.json');
        rmdir($rootDir);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit --filter testTrustState
```
Expected: FAIL — constructor signature mismatch and `trust_state` key absent.

- [ ] **Step 3: Update `SkillDiscovery` — pure annotation, no gating**

Modify `src/SkillDiscovery.php` constructor:

```php
public function __construct(
    private readonly IOInterface $io,
    private readonly ?\Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider $packages = null,
    private readonly ?\Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager $trust = null,
) {
}
```

Inside the main loop, after `declaresSkills()` and after building the skill row, annotate trust state. **Do not call `decide()`** here — that's reserved for the install boundary.

```php
foreach ($iter as $pkg) {
    if (!$pkg->declaresSkills()) {
        continue;
    }

    $packageSkills = $this->discoverSkillsFromPackage($pkg->name, $pkg->installPath, $pkg->version);

    foreach ($packageSkills as $skill) {
        $skill['trust_state'] = $this->resolveTrustState($pkg->name);

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
```

Add helper:

```php
private function resolveTrustState(string $packageName): string
{
    if ($this->trust === null) {
        return 'allowed'; // legacy callers without trust manager get the old behavior
    }
    if (!$this->trust->hasDecision($packageName)) {
        return 'pending';
    }
    return $this->trust->isAllowed($packageName) ? 'allowed' : 'denied';
}
```

Update the return-type docblock on `discoverAllSkills()`:

```php
/**
 * @return array<int, array{name: string, description: string, location: string, package: string, version: string, file: string, trust_state: string}>
 */
```

- [ ] **Step 4: Update `SkillPlugin::updateAgentsMd()` to seed → discover → filter**

Modify `src/SkillPlugin.php`:

```php
public function updateAgentsMd(Event $event): void
{
    try {
        $projectRoot = getcwd();
        if ($projectRoot === false) {
            throw new \RuntimeException('Could not determine project root directory.');
        }

        $provider = new \Netresearch\ComposerAgentSkillPlugin\Package\InstalledVersionsProvider();
        $trust = new \Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager($this->io, $projectRoot);

        // Seed legacy type: ai-agent-skill packages on first run (implicit trust).
        $legacyPackages = [];
        foreach ($provider->iterAllPackages() as $pkg) {
            if ($pkg->type === 'ai-agent-skill') {
                $legacyPackages[] = $pkg->name;
            }
        }
        $trust->seedIfAbsent($legacyPackages);

        $discovery = new SkillDiscovery($this->io, $provider, $trust);
        $allSkills = $discovery->discoverAllSkills();

        // Gate: prompt for pending packages, drop denied ones.
        $allowedSkills = [];
        $pendingPackages = [];
        foreach ($allSkills as $skill) {
            if ($skill['trust_state'] === 'allowed') {
                $allowedSkills[] = $skill;
                continue;
            }
            if ($skill['trust_state'] === 'denied') {
                continue;
            }
            // pending — call decide() (the only place that may prompt)
            if ($trust->decide($skill['package'])) {
                $allowedSkills[] = $skill;
            } else {
                $pendingPackages[$skill['package']] = true;
            }
        }

        $generator = new AgentsMdGenerator();
        $agentsMdPath = $projectRoot . DIRECTORY_SEPARATOR . 'AGENTS.md';
        $generator->updateAgentsMd($agentsMdPath, $allowedSkills);

        $skillCount = count($allowedSkills);
        if ($skillCount > 0) {
            $this->io->write(sprintf(
                '<info>AI Agent Skills updated: %d skill%s registered in AGENTS.md</info>',
                $skillCount,
                $skillCount === 1 ? '' : 's'
            ));
        }
        if (count($pendingPackages) > 0) {
            $this->io->write(sprintf(
                '<comment>%d package%s not registered (trust pending). Run composer install interactively to be prompted.</comment>',
                count($pendingPackages),
                count($pendingPackages) === 1 ? '' : 's'
            ));
        }
    } catch (\Exception $e) {
        $this->io->writeError(sprintf(
            '<error>AI Agent Skill Plugin error: %s</error>',
            $e->getMessage()
        ));
    }
}
```

- [ ] **Step 5: Update existing fixture-driven test that asserts skill row shape**

If `testDiscoversLibraryBundledSkill` (Task 5) inspects the row, add `trust_state` to the assertions or remove the strict count assertion.

- [ ] **Step 6: Run full suite**

```bash
vendor/bin/phpunit
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/SkillDiscovery.php src/SkillPlugin.php tests/Unit/SkillDiscoveryTest.php
git commit -S --signoff -m "feat: annotate skills with trust_state; gate AGENTS.md at install boundary"
```

---

## Task 10: Surface `trust_state` in `composer list-skills`

The skill rows already carry `trust_state` from Task 9. The command just renders it. **No prompts ever fire from this command** — it uses `discoverAllSkills()` exclusively, which is pure.

**Files:**
- Modify: `src/Commands/ListSkillsCommand.php`
- Test: `tests/Integration/ListSkillsCommandTest.php` (new)

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/ListSkillsCommandTest.php`:

```php
<?php
declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Integration;

use Composer\IO\BufferIO;
use Netresearch\ComposerAgentSkillPlugin\Commands\ListSkillsCommand;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider;
use Netresearch\ComposerAgentSkillPlugin\SkillDiscovery;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ListSkillsCommandTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/list-skills-' . uniqid();
        mkdir($this->rootDir);
    }

    protected function tearDown(): void
    {
        $cj = $this->rootDir . '/composer.json';
        if (file_exists($cj)) {
            unlink($cj);
        }
        if (is_dir($this->rootDir)) {
            rmdir($this->rootDir);
        }
    }

    public function testEmptyMessageWhenNoSkills(): void
    {
        $provider = new class implements PackageProvider {
            public function iterAllPackages(): iterable
            {
                yield from [];
            }
        };
        $trust = new SkillTrustManager(new BufferIO(), $this->rootDir);
        $discovery = new SkillDiscovery(new BufferIO(), $provider, $trust);

        $tester = new CommandTester(new ListSkillsCommand($discovery, $trust));
        $tester->execute([]);
        self::assertStringContainsString('No AI agent skills', $tester->getDisplay());
    }

    public function testShowsAllowedAndPendingColumns(): void
    {
        $fixturePath = realpath(__DIR__ . '/../Fixtures/library-with-skill');
        self::assertNotFalse($fixturePath);

        $provider = new class ($fixturePath) implements PackageProvider {
            public function __construct(private string $path) {}
            public function iterAllPackages(): iterable
            {
                yield new PackageInfo('allowed/lib', $this->path, '1.0.0', 'library', ['ai-agent-skill' => 'SKILL.md']);
                yield new PackageInfo('pending/lib', $this->path, '1.0.0', 'library', ['ai-agent-skill' => 'SKILL.md']);
            }
        };

        file_put_contents($this->rootDir . '/composer.json', json_encode([
            'extra' => ['ai-agent-skill' => ['allow-skills' => ['allowed/lib' => true]]],
        ]));

        $trust = new SkillTrustManager(new BufferIO(), $this->rootDir);
        $discovery = new SkillDiscovery(new BufferIO(), $provider, $trust);

        $tester = new CommandTester(new ListSkillsCommand($discovery, $trust));
        $tester->execute([]);
        $output = $tester->getDisplay();

        self::assertStringContainsString('allowed/lib', $output);
        self::assertStringContainsString('[allowed]', $output);
        self::assertStringContainsString('pending/lib', $output);
        self::assertStringContainsString('[pending]', $output);
        self::assertStringContainsString('1 pending', $output);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit tests/Integration/ListSkillsCommandTest.php
```
Expected: FAIL — constructor of `ListSkillsCommand` doesn't take args.

- [ ] **Step 3: Inject discovery + trust into the command**

Modify `src/Commands/ListSkillsCommand.php`:

```php
public function __construct(
    private readonly ?SkillDiscovery $discovery = null,
    private readonly ?\Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager $trust = null,
) {
    parent::__construct();
}

protected function execute(InputInterface $input, OutputInterface $output): int
{
    $helperSet = $this->getHelperSet() ?? new HelperSet([new QuestionHelper()]);
    $io = new ConsoleIO($input, $output, $helperSet);

    // Production path: build dependencies from runtime state if not injected.
    $rootDir = getcwd();
    if ($rootDir === false) {
        $rootDir = sys_get_temp_dir();
    }
    $trust = $this->trust ?? new \Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager($io, $rootDir);
    $discovery = $this->discovery ?? new SkillDiscovery(
        $io,
        new \Netresearch\ComposerAgentSkillPlugin\Package\InstalledVersionsProvider(),
        $trust,
    );

    $skills = $discovery->discoverAllSkills();

    if ($skills === []) {
        $output->writeln('');
        $output->writeln(' <fg=yellow>[WARNING]</> No AI agent skills found in installed packages.');
        $output->writeln('');
        return self::SUCCESS;
    }

    usort($skills, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

    $output->writeln('');
    $output->writeln('Available AI Agent Skills:');
    $output->writeln('');

    $maxNameLength = max(array_map(fn (array $s): int => strlen($s['name']), $skills));
    $maxPackageLength = max(array_map(fn (array $s): int => strlen($s['package']), $skills));

    $pending = 0;
    foreach ($skills as $skill) {
        $state = $skill['trust_state'] ?? 'allowed';
        $tag = match ($state) {
            'allowed' => '<fg=green>[allowed]</>',
            'denied'  => '<fg=red>[denied]</>',
            default   => '<fg=yellow>[pending]</>',
        };
        if ($state === 'pending') {
            $pending++;
        }
        $output->writeln(sprintf(
            '  %-' . $maxNameLength . 's  %-' . $maxPackageLength . 's  %-10s  %s',
            $skill['name'],
            $skill['package'],
            $skill['version'],
            $tag,
        ));
    }

    $output->writeln('');
    $output->writeln(sprintf(
        '%d skill%s available. Use \'composer read-skill <name>\' for details.',
        count($skills),
        count($skills) === 1 ? '' : 's',
    ));
    if ($pending > 0) {
        $output->writeln(sprintf(
            '<comment>%d pending — run `composer install` interactively to be prompted.</comment>',
            $pending,
        ));
    }
    $output->writeln('');

    return self::SUCCESS;
}
```

- [ ] **Step 4: Run full suite**

```bash
vendor/bin/phpunit
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Commands/ListSkillsCommand.php tests/Integration/ListSkillsCommandTest.php
git commit -S --signoff -m "feat: list-skills shows trust state column without prompting"
```

---

## Task 11: PHPStan + lint pass

**Files:**
- All new files

- [ ] **Step 1: Run PHPStan**

```bash
vendor/bin/phpstan analyse --memory-limit=512M
```
Expected: PASS at level 8 (no errors).

If errors appear, fix the offending class/property type hints. Most likely:
- `array<string, mixed>` annotations on `$extra`
- `iterable<PackageInfo>` on the provider interface

- [ ] **Step 2: Re-run full suite**

```bash
vendor/bin/phpunit
```
Expected: all green.

- [ ] **Step 3: Commit if any fixes were made**

```bash
git add -p
git commit -S --signoff -m "chore: satisfy PHPStan level 8 for new modules"
```

---

## Task 12: Documentation — README, CHANGELOG, PRD

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`
- Modify: `docs/PRD.md`

- [ ] **Step 1: Update README.md**

Replace the "Creating Skill Packages" section (`## Creating Skill Packages`) with two subsections:

```markdown
## Creating Skill Packages

There are two ways to ship skills:

### Option A: Library bundles a skill (recommended for libraries)

Any package — regardless of `type` — can ship skills by declaring `extra.ai-agent-skill`:

\```json
{
  "name": "vendor/my-library",
  "type": "library",
  "extra": {
    "ai-agent-skill": "skills/my-helper.md"
  }
}
\```

This is the same pattern `phpstan/extension-installer` uses for PHPStan extensions: the library opts in via `extra`, no special package type required.

### Option B: Dedicated skill package

A package whose only purpose is shipping skills can use `type: ai-agent-skill` and place `SKILL.md` in the package root. No `extra` config needed.
```

Add a new top-level section after "Quick Start":

```markdown
## Trust Model

Skills are instructions an AI agent will follow in your project. To prevent transitive dependencies from silently injecting skills, the plugin asks before registering skills from a new package — modeled on Composer's own `allow-plugins` flow.

First time a new package wants to register skills, you'll see:

\```
Package "vendor/foo" wants to register AI agent skills.
Skills are instructions an AI agent will follow in your project.
Allow this package to register skills?
  [y] Yes — allow & persist (writes to composer.json)
  [n] No — deny & persist (suppress future prompts)
  [a] Allow for this session only
  [d] Discard — do not allow, do not write
(defaults to n) [y,n,a,d]:
\```

Decisions persist under `extra.ai-agent-skill.allow-skills` in your root `composer.json`:

\```json
{
  "extra": {
    "ai-agent-skill": {
      "allow-skills": {
        "vendor/foo": true,
        "vendor/bar": false,
        "trusted-org/*": true
      }
    }
  }
}
\```

Glob patterns (`vendor/*`) are supported. Pre-authorize a package non-interactively (slashes in the package name require the `--json` form so Composer doesn't interpret them as nested keys):

\```bash
composer config --json extra.ai-agent-skill.allow-skills '{"vendor/foo": true}'
\```

In non-interactive mode (`composer install --no-interaction`, CI), packages without an explicit decision are skipped with a warning — the plugin never auto-trusts on your behalf.

### Migrating from earlier versions

If you already had `type: ai-agent-skill` packages installed before upgrading, the first run after upgrade will **auto-seed** them as trusted. Reasoning: you already chose to `composer require` them, so denying their skills retroactively would be more surprising than allowing. The seeded entries appear in `extra.ai-agent-skill.allow-skills` and you can flip any of them to `false` later.
```

- [ ] **Step 2: Update CHANGELOG.md**

Prepend under `## [Unreleased]`:

```markdown
### Added
- **Universal skill discovery**: any Composer package can now ship skills via `extra.ai-agent-skill`, regardless of its declared `type`. Closes [#42](https://github.com/netresearch/composer-agent-skill-plugin/issues/42).
- **Trust prompt**: first-time discovery from a new package prompts the user (`y`/`n`/`a`/`d`) before registering its skills. Decisions persist in root `composer.json` under `extra.ai-agent-skill.allow-skills` with glob support, mirroring Composer's `config.allow-plugins`.
- **Auto-seeding for upgrades**: existing `type: ai-agent-skill` packages installed before this version are auto-trusted on first run (you already chose to require them). No re-prompt avalanche on upgrade.
- `composer list-skills` now shows trust state (`[allowed]` / `[pending]` / `[denied]`) per skill and a footer count of pending packages. The command is purely informational and never prompts.
- `SkillTrustManager`, `PackageProvider`, and `TrustDecision` abstractions for testability.

### Changed
- `SkillDiscovery` no longer filters by package `type`. Legacy `type: ai-agent-skill` packages with a root `SKILL.md` continue to work unchanged.
- `SkillDiscovery::discoverAllSkills()` is now pure — it enumerates every declared skill with a `trust_state` field but never prompts. Gating happens at the install/update boundary in `SkillPlugin::updateAgentsMd()` only.
- Non-interactive `composer install` now skips untrusted skill packages with a `composer config --json` hint instead of registering them silently.
```

- [ ] **Step 3: Update PRD addendum**

Append to `docs/PRD.md`:

```markdown
---

## Addendum: Universal Discovery & Trust (v0.2.0)

Originally the plugin required packages to declare `type: ai-agent-skill`. Issue [#42](https://github.com/netresearch/composer-agent-skill-plugin/issues/42) showed this is too restrictive — a library's `type` is already taken by its primary identity (`library`, `symfony-bundle`, `typo3-cms-extension`, etc.), and forcing maintainers to ship a separate companion package for every library that wants to bundle a skill doubles the maintenance surface.

### Discovery change
Discovery now iterates **all** installed packages and selects those that either:
1. Declare `extra.ai-agent-skill` (the new primary path; mirrors `phpstan/extension-installer`'s `extra.phpstan.includes`), **or**
2. Carry the legacy `type: ai-agent-skill` (preserved for existing pure-skill packages).

### Trust change
Broadening discovery means any transitive dependency could potentially register skills. To keep the security story intact, the plugin requires per-package opt-in before reading SKILL.md content — the same shape Composer uses for `allow-plugins`:

- Interactive mode: `[y,n,a,d]` prompt; `y`/`n` persist, `a` is session-only, `d` discards.
- Non-interactive mode: skip with a `composer config` hint (gentler than Composer's hard-fail; CI stays deterministic).
- Persistence: `extra.ai-agent-skill.allow-skills` in the root `composer.json`, glob patterns supported.
```

- [ ] **Step 4: Verify all docs render**

```bash
git diff README.md CHANGELOG.md docs/PRD.md | head -60
```

- [ ] **Step 5: Commit**

```bash
git add README.md CHANGELOG.md docs/PRD.md
git commit -S --signoff -m "docs: document universal discovery and trust model"
```

---

## Task 13: Open the pull request

- [ ] **Step 1: Push branch**

```bash
git push -u origin feat/universal-discovery-with-trust
```

- [ ] **Step 2: Open PR linked to issue #42**

```bash
gh pr create --title "feat: universal skill discovery with trust prompt" --body "$(cat <<'EOF'
## Summary

Closes #42.

- Drops the `type: ai-agent-skill` requirement. Any package can now ship skills via `extra.ai-agent-skill` — same model as `phpstan/extension-installer`. Legacy `type: ai-agent-skill` packages continue to work.
- Adds a per-package trust prompt modeled on Composer's `allow-plugins` flow (`y` allow & persist / `n` deny & persist / `a` session-only / `d` discard). Decisions persist under `extra.ai-agent-skill.allow-skills` with glob support.
- Non-interactive runs (`--no-interaction`, CI) skip untrusted packages with a `composer config` hint — never auto-trusts.

## Test plan
- [ ] `vendor/bin/phpunit` — all green
- [ ] `vendor/bin/phpstan analyse` — clean at level 8
- [ ] Manual: install a `type: library` package with `extra.ai-agent-skill`, confirm prompt fires, choose `y`, re-run install, confirm no re-prompt
- [ ] Manual: in non-interactive mode, confirm package is skipped with hint
EOF
)"
```

- [ ] **Step 3: Verify CI starts**

```bash
gh pr checks --watch
```

---

## Self-Review Checklist

- [ ] Every spec point in the conversation maps to a task (universal discovery → T4-5, trust prompt → T6-9, persistence → T8, glob → T7, non-interactive policy → T8, list-skills surface → T10, docs → T12).
- [ ] No "TBD" / "implement later" placeholders.
- [ ] Type names consistent: `PackageInfo`, `PackageProvider`, `InstalledVersionsProvider`, `SkillTrustManager`, `TrustDecision` used identically everywhere.
- [ ] Method names consistent: `iterAllPackages()`, `declaresSkills()`, `decide()`, `isAllowed()`, `hasDecision()`.
- [ ] Each task ends in a green test run + commit.
