<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\DirectSkills\DirectSkillsPathGuard;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;
use PHPUnit\Framework\TestCase;

final class DirectSkillsPathGuardTest extends TestCase
{
    public function testRejectsParentTraversalInInstallPath(): void
    {
        $this->expectException(DirectSkillsException::class);
        DirectSkillsPathGuard::assertLockRelativePosix('install-path', 'vendor/../etc/passwd', false);
    }

    public function testAllowsDotPathInSource(): void
    {
        DirectSkillsPathGuard::assertLockRelativePosix('path', '.', true);
        $this->addToAssertionCount(1);
    }

    public function testNormalizesLeadingDotSlash(): void
    {
        DirectSkillsPathGuard::assertLockRelativePosix('url', './vendor-fixtures/alpha', false);
        $this->addToAssertionCount(1);
    }
}
