<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit\Trust;

use Netresearch\ComposerAgentSkillPlugin\Trust\TrustState;
use PHPUnit\Framework\TestCase;

final class TrustStateTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame('allowed', TrustState::Allowed->value);
        self::assertSame('denied', TrustState::Denied->value);
        self::assertSame('pending', TrustState::Pending->value);
    }

    public function testIsRegisterable(): void
    {
        self::assertTrue(TrustState::Allowed->isRegisterable());
        self::assertFalse(TrustState::Denied->isRegisterable());
        self::assertFalse(TrustState::Pending->isRegisterable());
    }

    public function testTag(): void
    {
        self::assertSame('<fg=green>[allowed]</>', TrustState::Allowed->tag());
        self::assertSame('<fg=red>[denied]</>', TrustState::Denied->tag());
        self::assertSame('<fg=yellow>[pending]</>', TrustState::Pending->tag());
    }
}
