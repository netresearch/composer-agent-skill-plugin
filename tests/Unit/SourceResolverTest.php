<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\Source\SourceResolver;
use PHPUnit\Framework\TestCase;

final class SourceResolverTest extends TestCase
{
    private SourceResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new SourceResolver();
    }

    public function testGithubShorthand(): void
    {
        $r = $this->resolver->resolve('octocat/Hello-World');
        self::assertSame('octocat/Hello-World', $r->name);
        self::assertSame('github', $r->type);
        self::assertSame('https://github.com/octocat/Hello-World.git', $r->url);
        self::assertNull($r->path);
    }

    public function testGithubTreeUrlExtractsRefAndPath(): void
    {
        $r = $this->resolver->resolve('https://github.com/foo/bar/tree/main/skills/hello');
        self::assertSame('foo/bar', $r->name);
        self::assertSame('main', $r->ref);
        self::assertSame('skills/hello', $r->path);
    }

    public function testEmptyInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->resolve('   ');
    }

    public function testNonsenseInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->resolve('not-a-valid-source-!!!');
    }

    public function testExistingDirectoryResolvesAsPath(): void
    {
        $dir = sys_get_temp_dir() . '/src-resolver-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        try {
            $r = $this->resolver->resolve($dir);
            self::assertSame('path', $r->type);
            self::assertNull($r->url);
            self::assertNotNull($r->path);
            self::assertDirectoryExists($r->path);
        } finally {
            @rmdir($dir);
        }
    }
}
