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
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\DirectSkillsCoordinator;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;
use Netresearch\ComposerAgentSkillPlugin\Discovery\DirectInstalledSkillDiscovery;
use Netresearch\ComposerAgentSkillPlugin\Package\InstalledVersionsProvider;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;

/**
 * Main plugin class for AI Agent Skill management.
 *
 * Implements Composer plugin interfaces to hook into install/update events
 * and automatically maintain the AGENTS.md file with discovered skills.
 */
final class SkillPlugin implements PluginInterface, Capable, EventSubscriberInterface
{
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @return array<string, class-string>
     */
    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => CommandCapability::class,
        ];
    }

    /**
     * @return array<string, string|array<int, string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstall',
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdate',
        ];
    }

    public function onPostInstall(Event $event): void
    {
        try {
            $composerJsonPath = $event->getComposer()->getConfig()->getConfigSource()->getName();
            $projectRoot = dirname($composerJsonPath);
            (new DirectSkillsCoordinator())->installPinned($this->io, $projectRoot);
        } catch (DirectSkillsException $e) {
            $this->io->writeError(sprintf('<error>%s</error>', $e->getMessage()));
            throw $e;
        }
        $this->regenerateAgentsMdSafe($event);
    }

    public function onPostUpdate(Event $event): void
    {
        try {
            $composerJsonPath = $event->getComposer()->getConfig()->getConfigSource()->getName();
            $projectRoot = dirname($composerJsonPath);
            (new DirectSkillsCoordinator())->updateFloating($this->io, $projectRoot);
        } catch (DirectSkillsException $e) {
            $this->io->writeError(sprintf('<error>%s</error>', $e->getMessage()));
            throw $e;
        }
        $this->regenerateAgentsMdSafe($event);
    }

    /**
     * Update AGENTS.md with discovered (and trusted) skills from Composer packages and direct installs.
     *
     * Wrapped in try/catch so incidental failures never block Composer — unlike direct-skills lock sync.
     */
    private function regenerateAgentsMdSafe(Event $event): void
    {
        try {
            $composer = $event->getComposer();
            $composerJsonPath = $composer->getConfig()->getConfigSource()->getName();
            $projectRoot = dirname($composerJsonPath);

            $provider = new InstalledVersionsProvider();
            $rootPackageName = $composer->getPackage()->getName();
            $trust = SkillTrustManager::forComposerJson(
                $this->io,
                $composerJsonPath,
                $rootPackageName,
            );

            $rootRequires = $composer->getPackage()->getRequires();
            $rootDevRequires = $composer->getPackage()->getDevRequires();
            $directNames = array_merge(array_keys($rootRequires), array_keys($rootDevRequires));
            $firstRunInput = \Netresearch\ComposerAgentSkillPlugin\Trust\FirstRunInput::buildForFirstRun(
                $provider,
                $directNames,
            );
            $trust->applyFirstRunPolicy($firstRunInput);

            $discovery = new SkillDiscovery($this->io, $provider, $trust);
            $packageSkills = $discovery->discoverAllSkills();

            $directDiscover = new DirectInstalledSkillDiscovery();
            $directSkills = $directDiscover->discoverInstalled($this->io, $trust, $projectRoot);

            $byName = [];
            foreach ($packageSkills as $skill) {
                $byName[$skill['name']] = 'composer package';
            }
            foreach ($directSkills as $skill) {
                if (isset($byName[$skill['name']])) {
                    $this->io->writeError(sprintf(
                        '<error>Duplicate skill name "%s" from %s and direct install. Fix composer.json / sources.</error>',
                        $skill['name'],
                        $byName[$skill['name']],
                    ));
                    throw new \RuntimeException(sprintf('Duplicate skill name: %s', $skill['name']));
                }
                $byName[$skill['name']] = 'direct skills';
            }

            $allSkills = array_merge($packageSkills, $directSkills);

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
            $this->io->writeError(sprintf(
                '<error>AI Agent Skill Plugin error: %s</error>',
                $e->getMessage()
            ));
            if ($this->io->isVerbose()) {
                $this->io->writeError((string) $e);
            }
        }
    }
}
