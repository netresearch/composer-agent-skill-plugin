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
     * Hooks into install and update; both fire `composer require` indirectly
     * (it runs an update internally). `composer create-project` covers itself
     * because it dispatches POST_INSTALL_CMD before its own POST_CREATE_PROJECT_CMD.
     *
     * Deliberately NOT subscribing to POST_AUTOLOAD_DUMP — `composer dump-autoload`
     * is invoked when developers regenerate autoload files, but vendored skills
     * are not normally edited in place. Subscribing would re-scan the package
     * tree on every dump for no behavioral gain.
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
            $composer = $event->getComposer();
            // Composer's ConfigSource::getName() returns the absolute composer.json
            // path. This is the right source — getcwd() breaks under
            // `composer --working-dir=…`, daemon contexts, and in tests where
            // the working directory drifts.
            $composerJsonPath = $composer->getConfig()->getConfigSource()->getName();
            $projectRoot = dirname($composerJsonPath);

            $provider = new \Netresearch\ComposerAgentSkillPlugin\Package\InstalledVersionsProvider();
            $rootPackageName = $composer->getPackage()->getName();
            $trust = \Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager::forComposerJson(
                $this->io,
                $composerJsonPath,
                $rootPackageName,
            );

            // First-run policy: when the trust map is missing AND there are legacy
            // type:ai-agent-skill packages, ask the user once how to seed
            // (none/direct/all). Defaults to 'none' (strict) — including in
            // non-interactive mode, which also emits a recovery hint per package.
            $rootRequires = $composer->getPackage()->getRequires();
            $rootDevRequires = $composer->getPackage()->getDevRequires();
            $directNames = array_merge(array_keys($rootRequires), array_keys($rootDevRequires));
            $firstRunInput = \Netresearch\ComposerAgentSkillPlugin\Trust\FirstRunInput::buildForFirstRun(
                $provider,
                $directNames,
            );
            $trust->applyFirstRunPolicy($firstRunInput);

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
        } catch (\Throwable $e) {
            // Catch \Throwable (not \Exception) so PHP 8 \Error subclasses
            // like \ValueError (e.g. NUL byte in a path arg) don't bubble out
            // and abort the entire composer install. The plugin's failure
            // should never block dependency installation.
            $this->io->writeError(sprintf(
                '<error>AI Agent Skill Plugin error: %s</error>',
                $e->getMessage()
            ));
        }
    }
}
