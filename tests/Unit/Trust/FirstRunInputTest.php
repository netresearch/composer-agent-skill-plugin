<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Trust;

use Netresearch\ComposerAgentSkillPlugin\Package\PackageInfo;
use Netresearch\ComposerAgentSkillPlugin\Package\PackageProvider;
use Netresearch\ComposerAgentSkillPlugin\Trust\FirstRunInput;
use PHPUnit\Framework\TestCase;

final class FirstRunInputTest extends TestCase
{
    public function testEmptyWhenNoPackages(): void
    {
        $provider = new class () implements PackageProvider {
            public function iterAllPackages(): iterable
            {
                yield from [];
            }
        };
        $input = FirstRunInput::buildForFirstRun($provider, []);

        self::assertTrue($input->isEmpty());
        self::assertSame([], $input->legacyPackages);
        self::assertSame([], $input->directOnly());
    }

    public function testCollectsLegacyAiAgentSkillPackages(): void
    {
        $provider = $this->providerWith([
            ['vendor/skill-a', 'ai-agent-skill'],
            ['vendor/normal-lib', 'library'],
            ['vendor/skill-b', 'ai-agent-skill'],
        ]);
        $input = FirstRunInput::buildForFirstRun($provider, []);

        self::assertSame(['vendor/skill-a', 'vendor/skill-b'], $input->legacyPackages);
    }

    public function testIsDirectMatchesRootRequires(): void
    {
        $provider = $this->providerWith([
            ['vendor/direct-a', 'ai-agent-skill'],
            ['vendor/transitive', 'ai-agent-skill'],
        ]);
        $input = FirstRunInput::buildForFirstRun($provider, ['vendor/direct-a']);

        self::assertTrue($input->isDirect('vendor/direct-a'));
        self::assertFalse($input->isDirect('vendor/transitive'));
    }

    public function testDirectOnlyFiltersToDirectDeps(): void
    {
        $provider = $this->providerWith([
            ['vendor/direct-a', 'ai-agent-skill'],
            ['vendor/transitive-x', 'ai-agent-skill'],
            ['vendor/direct-b', 'ai-agent-skill'],
        ]);
        $input = FirstRunInput::buildForFirstRun($provider, ['vendor/direct-a', 'vendor/direct-b']);

        self::assertSame(['vendor/direct-a', 'vendor/direct-b'], $input->directOnly());
    }

    public function testDirectListContainingNonLegacyIsIgnored(): void
    {
        // root requires include packages that aren't of type ai-agent-skill;
        // those shouldn't poison the input.
        $provider = $this->providerWith([
            ['vendor/skill', 'ai-agent-skill'],
        ]);
        $input = FirstRunInput::buildForFirstRun($provider, ['vendor/skill', 'vendor/some-lib']);

        self::assertSame(['vendor/skill'], $input->legacyPackages);
        self::assertSame(['vendor/skill'], $input->directOnly());
    }

    /**
     * @param list<array{string, string}> $entries
     */
    private function providerWith(array $entries): PackageProvider
    {
        return new class ($entries) implements PackageProvider {
            /** @param list<array{string, string}> $entries */
            public function __construct(private array $entries)
            {
            }

            public function iterAllPackages(): iterable
            {
                foreach ($this->entries as [$name, $type]) {
                    yield new PackageInfo($name, '/tmp', '1.0.0', $type, []);
                }
            }
        };
    }
}
