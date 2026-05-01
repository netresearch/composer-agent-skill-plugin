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
            $trust = new \Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager($this->io, $projectRoot);

            // Auto-seed: existing type: ai-agent-skill packages are implicitly trusted
            // on first run. The user already chose to `composer require` them, so
            // re-prompting on every legacy package would be more surprising than allowing.
            $legacyPackages = [];
            foreach ($provider->iterAllPackages() as $pkg) {
                if ($pkg->type === 'ai-agent-skill') {
                    $legacyPackages[] = $pkg->name;
                }
            }
            $trust->seedIfAbsent($legacyPackages);

            $discovery = new SkillDiscovery($this->io, $provider, $trust);
            $allSkills = $discovery->discoverAllSkills();

            // Gate: prompt only for pending entries; drop denied ones.
            $allowedSkills = [];
            $deniedPackages = [];
            $pendingPackages = [];
            foreach ($allSkills as $skill) {
                if ($skill['trust_state'] === 'allowed') {
                    $allowedSkills[] = $skill;
                    continue;
                }
                if ($skill['trust_state'] === 'denied') {
                    $deniedPackages[$skill['package']] = true;
                    continue;
                }
                // pending — call decide() (the only place that may prompt).
                // After decide() we re-check the trust state so a freshly persisted
                // 'n' (deny) gets counted as denied, not pending.
                if ($trust->decide($skill['package'])) {
                    $allowedSkills[] = $skill;
                } elseif ($trust->hasDecision($skill['package']) && !$trust->isAllowed($skill['package'])) {
                    $deniedPackages[$skill['package']] = true;
                } else {
                    $pendingPackages[$skill['package']] = true;
                }
            }

            $generator = new AgentsMdGenerator();
            $agentsMdPath = $projectRoot . DIRECTORY_SEPARATOR . 'AGENTS.md';
            $generator->updateAgentsMd($agentsMdPath, $allowedSkills);

            $skillCount = count($allowedSkills);
            if ($skillCount > 0) {
                $this->io->write(sprintf(
                    '<info>AI Agent Skills updated: %d skill%s registered in AGENTS.md</info>',
                    $skillCount,
                    $skillCount === 1 ? '' : 's'
                ));
            }
            if (count($deniedPackages) > 0) {
                $this->io->write(sprintf(
                    '<comment>%d package%s not registered (trust denied).</comment>',
                    count($deniedPackages),
                    count($deniedPackages) === 1 ? '' : 's'
                ));
            }
            if (count($pendingPackages) > 0) {
                $this->io->write(sprintf(
                    '<comment>%d package%s not registered (trust pending). Run composer install interactively to be prompted.</comment>',
                    count($pendingPackages),
                    count($pendingPackages) === 1 ? '' : 's'
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
