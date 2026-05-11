<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Util;

use Symfony\Component\Process\Process;

final class GitCli
{
    /**
     * @param list<string> $args git arguments after `git`
     */
    public static function run(array $args, ?string $cwd = null, int $timeout = 600): Process
    {
        /** @var list<string> $cmd */
        $cmd = array_merge(['git'], $args);
        $p = new Process($cmd, $cwd, null, null, (float) $timeout);
        $p->run();

        return $p;
    }

    /**
     * @param list<string> $args git arguments after `git`
     */
    public static function mustRun(array $args, ?string $cwd = null, int $timeout = 600): string
    {
        $p = self::run($args, $cwd, $timeout);
        if (!$p->isSuccessful()) {
            throw new \RuntimeException(trim(sprintf(
                "git %s failed:\n%s\n%s",
                implode(' ', $args),
                $p->getErrorOutput(),
                $p->getOutput(),
            )));
        }

        return trim($p->getOutput());
    }

    public static function revParse(string $repoDir): string
    {
        return self::mustRun(['rev-parse', 'HEAD'], $repoDir);
    }
}
