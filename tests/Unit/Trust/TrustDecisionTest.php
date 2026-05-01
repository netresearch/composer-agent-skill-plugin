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

    public function testFromAnswerUppercaseAcceptedSameAsLowercase(): void
    {
        self::assertSame(TrustDecision::Allow, TrustDecision::fromAnswer('Y'));
    }

    public function testFromAnswerInvalidReturnsNull(): void
    {
        self::assertNull(TrustDecision::fromAnswer('x'));
    }

    public function testFromAnswerEmptyReturnsNull(): void
    {
        self::assertNull(TrustDecision::fromAnswer(''));
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
