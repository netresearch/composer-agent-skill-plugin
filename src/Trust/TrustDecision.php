<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Trust;

/**
 * The user's raw answer to a single trust prompt.
 *
 * Distinct from {@see TrustState}: TrustDecision is the four-way menu choice
 * (`y`/`n`/`a`/`d`) the user makes at one prompt invocation; TrustState is the
 * resolved trust policy carried downstream through discovery and gating. The
 * prompt produces a TrustDecision; the manager translates it into a persisted
 * map entry (or session-only entry, or no-op for Discard) and then exposes
 * the consequences via TrustState.
 */
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
