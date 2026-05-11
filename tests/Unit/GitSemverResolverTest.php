<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;
use Netresearch\ComposerAgentSkillPlugin\Util\GitRefCommitResolver;
use Netresearch\ComposerAgentSkillPlugin\Util\GitSemverResolver;
use PHPUnit\Framework\TestCase;

final class GitSemverResolverTest extends TestCase
{
    public function testLooksLikeSemverConstraint(): void
    {
        self::assertTrue(GitSemverResolver::looksLikeSemverConstraint('^1.2'));
        self::assertTrue(GitSemverResolver::looksLikeSemverConstraint('~2.0'));
        self::assertTrue(GitSemverResolver::looksLikeSemverConstraint('>=1,<2'));
        self::assertTrue(GitSemverResolver::looksLikeSemverConstraint('1.*'));
        self::assertFalse(GitSemverResolver::looksLikeSemverConstraint('main'));
        self::assertFalse(GitSemverResolver::looksLikeSemverConstraint('v1.2.3'));
    }

    public function testNormalizedVersionsToTagsMapsAnnotatedTags(): void
    {
        $ls = "abc12345\trefs/tags/v1.2.3\n"
            . "def67890\trefs/tags/v1.2.3^{}\n"
            . "deadbeef\trefs/tags/v1.2.6\n";
        $map = GitSemverResolver::normalizedVersionsToTags($ls);
        self::assertContains('v1.2.3', $map);
        self::assertContains('v1.2.6', $map);
    }

    public function testResolveToGitRefPassesThroughPlainBranch(): void
    {
        self::assertSame('main', GitSemverResolver::resolveToGitRef('https://example.com/x.git', 'main'));
    }

    public function testPickTagForConstraintHighestInRange(): void
    {
        $ls = "aaaaaaaa\trefs/tags/v1.2.3\nbbbbbbbb\trefs/tags/v1.2.6\ncccccccc\trefs/tags/v2.0.0\n";
        $map = GitSemverResolver::normalizedVersionsToTags($ls);
        $tag = GitSemverResolver::pickTagForConstraint('https://example.com/x.git', '^1.2', $map);
        self::assertSame('v1.2.6', $tag);
    }

    public function testPickTagForConstraintThrowsWhenUnsatisfiable(): void
    {
        $ls = "aaaaaaaa\trefs/tags/v3.0.0\n";
        $map = GitSemverResolver::normalizedVersionsToTags($ls);
        $this->expectException(DirectSkillsException::class);
        GitSemverResolver::pickTagForConstraint('https://example.com/x.git', '^1.2', $map);
    }

    public function testCommitsEquivalentNormalizesCaseAndPrefix(): void
    {
        self::assertTrue(GitRefCommitResolver::commitsEquivalent('AbCdEf1', 'abcdef123456'));
        self::assertTrue(GitRefCommitResolver::commitsEquivalent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'aaaaaaa'));
        self::assertFalse(GitRefCommitResolver::commitsEquivalent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'bbbbbbb'));
    }
}
