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
