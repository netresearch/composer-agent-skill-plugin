<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Trust;

/**
 * Trust state attached to each discovered skill.
 *
 * Distinct from {@see TrustDecision}: TrustDecision describes the user's
 * answer to a single prompt; TrustState describes the resolved policy for
 * a package at discovery time (taking persisted decisions, session-allows,
 * and missing-decision fallback into account).
 */
enum TrustState: string
{
    case Allowed = 'allowed';
    case Denied  = 'denied';
    case Pending = 'pending';

    public function isRegisterable(): bool
    {
        return $this === self::Allowed;
    }

    public function tag(): string
    {
        return match ($this) {
            self::Allowed => '<fg=green>[allowed]</>',
            self::Denied  => '<fg=red>[denied]</>',
            self::Pending => '<fg=yellow>[pending]</>',
        };
    }
}
