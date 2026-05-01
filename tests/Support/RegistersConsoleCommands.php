<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Support;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * Bridge for Symfony Console's command-registration API across versions.
 * Symfony 7.4 deprecated Application::add() in favour of Application::addCommand();
 * Symfony 8.0 removed add() entirely.
 */
trait RegistersConsoleCommands
{
    private static function registerCommand(Application $app, Command $command): void
    {
        if (method_exists($app, 'addCommand')) {
            $app->addCommand($command);
            return;
        }

        /** @phpstan-ignore-next-line method.deprecated */
        $app->add($command);
    }
}
