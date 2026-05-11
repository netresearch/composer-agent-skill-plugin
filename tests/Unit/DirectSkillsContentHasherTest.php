<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\DirectSkills\DirectSkillsContentHasher;
use PHPUnit\Framework\TestCase;

final class DirectSkillsContentHasherTest extends TestCase
{
    public function testTrustInExtraDoesNotChangeHash(): void
    {
        $hasher = new DirectSkillsContentHasher();
        $sourcesBlock = [
            'version' => 1,
            'install-dir' => 'vendor/agent-skills/installed',
            'sources-dir' => 'vendor/agent-skills/sources',
            'cache-dir' => 'vendor/agent-skills/cache',
            'sources' => [
                [
                    'name' => 'local/foo',
                    'type' => 'path',
                    'path' => './skills/foo',
                    'skills' => ['*'],
                ],
            ],
        ];

        $withTrust = $hasher->hashFromExtraArray([
            'ai-agent-skills' => $sourcesBlock + [
                'trust' => [
                    'direct:local/foo/bar' => true,
                ],
            ],
        ]);
        $withoutTrust = $hasher->hashFromExtraArray([
            'ai-agent-skills' => $sourcesBlock,
        ]);

        self::assertSame($withTrust, $withoutTrust);
    }

    public function testChangingSourceChangesHash(): void
    {
        $hasher = new DirectSkillsContentHasher();
        $a = $hasher->hashFromExtraArray([
            'ai-agent-skills' => [
                'version' => 1,
                'sources' => [
                    ['name' => 'a/b', 'type' => 'github', 'url' => 'https://github.com/a/b.git', 'ref' => 'main', 'skills' => ['x']],
                ],
            ],
        ]);
        $b = $hasher->hashFromExtraArray([
            'ai-agent-skills' => [
                'version' => 1,
                'sources' => [
                    ['name' => 'a/b', 'type' => 'github', 'url' => 'https://github.com/a/b.git', 'ref' => 'develop', 'skills' => ['x']],
                ],
            ],
        ]);

        self::assertNotSame($a, $b);
    }
}
