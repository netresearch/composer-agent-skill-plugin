<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Netresearch\ComposerAgentSkillPlugin\Trust\TrustState;

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
        return [
            CommandProvider::class => CommandCapability::class,
        ];
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
     * Update AGENTS.md with discovered (and trusted) skills.
     *
     * Called automatically after composer install/update. This is the only
     * place where SkillTrustManager::decide() may prompt the user — pure
     * discovery (used by `composer list-skills`) never triggers prompts.
     */
    public function updateAgentsMd(Event $event): void
    {
        try {
            $projectRoot = getcwd();
            if ($projectRoot === false) {
                throw new \RuntimeException('Could not determine project root directory.');
            }

            $provider = new \Netresearch\ComposerAgentSkillPlugin\Package\InstalledVersionsProvider();
            $rootPackageName = $event->getComposer()->getPackage()->getName();
            $trust = new \Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager(
                $this->io,
                $projectRoot,
                $rootPackageName,
            );

            // First-run policy: when the trust map is missing AND there are legacy
            // type:ai-agent-skill packages, ask the user once how to seed
            // (none/direct/all). Defaults to 'none' (strict) — including in
            // non-interactive mode, which also emits a recovery hint per package.
            $rootRequires = $event->getComposer()->getPackage()->getRequires();
            $rootDevRequires = $event->getComposer()->getPackage()->getDevRequires();
            $directNames = array_merge(array_keys($rootRequires), array_keys($rootDevRequires));
            $directSet = array_fill_keys($directNames, true);

            $legacyPackages = [];
            $directLegacy = [];
            foreach ($provider->iterAllPackages() as $pkg) {
                if ($pkg->type === 'ai-agent-skill') {
                    $legacyPackages[] = $pkg->name;
                    if (isset($directSet[$pkg->name])) {
                        $directLegacy[] = $pkg->name;
                    }
                }
            }
            $trust->applyFirstRunPolicy($legacyPackages, $directLegacy);

            $discovery = new SkillDiscovery($this->io, $provider, $trust);
            $allSkills = $discovery->discoverAllSkills();

            $gate = new SkillGate($trust);
            $result = $gate->gate($allSkills);

            $generator = new AgentsMdGenerator();
            $agentsMdPath = $projectRoot . DIRECTORY_SEPARATOR . 'AGENTS.md';
            $generator->updateAgentsMd($agentsMdPath, $result->allowed);

            $skillCount = count($result->allowed);
            if ($skillCount > 0) {
                $this->io->write(sprintf(
                    '<info>AI Agent Skills updated: %d skill%s registered in AGENTS.md</info>',
                    $skillCount,
                    $skillCount === 1 ? '' : 's'
                ));
            }
            if (count($result->denied) > 0) {
                $this->io->write(sprintf(
                    '<comment>%d package%s not registered (trust denied).</comment>',
                    count($result->denied),
                    count($result->denied) === 1 ? '' : 's'
                ));
            }
            if (count($result->pending) > 0) {
                $this->io->write(sprintf(
                    '<comment>%d package%s not registered (trust pending). Run composer install interactively to be prompted.</comment>',
                    count($result->pending),
                    count($result->pending) === 1 ? '' : 's'
                ));
            }
        } catch (\Exception $e) {
            $this->io->writeError(sprintf(
                '<error>AI Agent Skill Plugin error: %s</error>',
                $e->getMessage()
            ));
        }
    }
}
