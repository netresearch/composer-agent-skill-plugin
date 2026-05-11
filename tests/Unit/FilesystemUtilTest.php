<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\Util\FilesystemUtil;
use PHPUnit\Framework\TestCase;

final class FilesystemUtilTest extends TestCase
{
    public function testRelativePathStrictPrefixDoesNotMatchSiblingDirectory(): void
    {
        $base = sys_get_temp_dir() . '/fsutil-prefix-' . bin2hex(random_bytes(4));
        $project = $base . '/proj';
        $sibling = $base . '/proj-other';
        $inside = $project . '/skills';
        mkdir($inside, 0777, true);
        mkdir($sibling . '/trap', 0777, true);
        try {
            $got = FilesystemUtil::relativePosixFromProjectRoot($project, $sibling . DIRECTORY_SEPARATOR . 'trap');
            self::assertFalse(str_starts_with($got, './'), $got);
            self::assertStringContainsString('proj-other', $got);
        } finally {
            @rmdir($sibling . '/trap');
            @rmdir($sibling);
            @rmdir($inside);
            @rmdir($project);
            @rmdir($base);
        }
    }

    public function testRelativePathUnderProjectUsesDotSlash(): void
    {
        $base = sys_get_temp_dir() . '/fsutil-under-' . bin2hex(random_bytes(4));
        $project = $base . '/proj';
        $inside = $project . '/skills';
        mkdir($inside, 0777, true);
        try {
            $got = FilesystemUtil::relativePosixFromProjectRoot($project, $inside);
            self::assertSame('./skills', $got);
        } finally {
            @rmdir($inside);
            @rmdir($project);
            @rmdir($base);
        }
    }

    public function testRelativePathProjectRootIsDot(): void
    {
        $base = sys_get_temp_dir() . '/fsutil-root-' . bin2hex(random_bytes(4));
        mkdir($base, 0777, true);
        try {
            self::assertSame('.', FilesystemUtil::relativePosixFromProjectRoot($base, $base));
        } finally {
            @rmdir($base);
        }
    }
}
