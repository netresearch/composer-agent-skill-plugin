<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin;

use Composer\Plugin\Capability\CommandProvider;
use Netresearch\ComposerAgentSkillPlugin\Commands\ListSkillsCommand;
use Netresearch\ComposerAgentSkillPlugin\Commands\ReadSkillCommand;

final class CommandCapability implements CommandProvider
{
    /**
     * Get all commands provided by this plugin.
     *
     * @return array<\Composer\Command\BaseCommand>
     */
    public function getCommands(): array
    {
        return [
            new ListSkillsCommand(),
            new ReadSkillCommand(),
        ];
    }
}
