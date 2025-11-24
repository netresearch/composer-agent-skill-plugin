<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Main plugin class for AI Agent Skill management.
 *
 * Implements Composer plugin interfaces to hook into install/update events
 * and automatically maintain the AGENTS.md file with discovered skills.
 */
final class SkillPlugin implements PluginInterface, Capable, EventSubscriberInterface
{
    private IOInterface $io;

    /**
     * Activate the plugin.
     *
     * Called when the plugin is activated by Composer.
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    /**
     * Deactivate the plugin.
     *
     * Called when the plugin is deactivated.
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // No cleanup needed on deactivation
    }

    /**
     * Uninstall the plugin.
     *
     * Called when the plugin is uninstalled.
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // No cleanup needed on uninstall
    }

    /**
     * Get plugin capabilities.
     *
     * @return array<string, class-string>
     */
    public function getCapabilities(): array
    {
        // Command provider will be added in Phase 3
        return [];
    }

    /**
     * Get subscribed events.
     *
     * Registers event handlers for post-install and post-update commands.
     *
     * @return array<string, string|array<int, string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'updateAgentsMd',
            ScriptEvents::POST_UPDATE_CMD => 'updateAgentsMd',
        ];
    }

    /**
     * Update AGENTS.md with discovered skills.
     *
     * Called automatically after composer install/update.
     */
    public function updateAgentsMd(Event $event): void
    {
        try {
            // Discover all skills from installed packages
            $discovery = new SkillDiscovery($this->io);
            $skills = $discovery->discoverAllSkills();

            // Generate and update AGENTS.md
            $generator = new AgentsMdGenerator();
            $projectRoot = getcwd();
            if ($projectRoot === false) {
                throw new \RuntimeException('Could not determine project root directory.');
            }

            $agentsMdPath = $projectRoot . DIRECTORY_SEPARATOR . 'AGENTS.md';
            $generator->updateAgentsMd($agentsMdPath, $skills);

            // Output success message
            $skillCount = count($skills);
            if ($skillCount > 0) {
                $this->io->write(
                    sprintf(
                        '<info>AI Agent Skills updated: %d skill%s registered in AGENTS.md</info>',
                        $skillCount,
                        $skillCount === 1 ? '' : 's'
                    )
                );
            }
        } catch (\Exception $e) {
            $this->io->writeError(
                sprintf(
                    '<error>AI Agent Skill Plugin error: %s</error>',
                    $e->getMessage()
                )
            );
        }
    }
}
