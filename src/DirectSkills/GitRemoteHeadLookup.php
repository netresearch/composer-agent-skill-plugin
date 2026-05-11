<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\DirectSkills;

/**
 * Resolves the commit a floating {@see SourceEntry} ref would point to on the remote (for outdated checks).
 */
interface GitRemoteHeadLookup
{
    /**
     * @throws \Throwable when git cannot resolve the ref (network, missing ref, etc.)
     */
    public function resolveRemoteCommit(string $url, string $storedRef): string;
}
