<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\DirectSkills;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;

/**
 * Appends direct-skill outdated rows after {@code composer outdated} (which runs {@code show --latest --outdated}).
 */
final class DirectSkillsOutdatedComposerHook
{
    private static bool $shutdownRegistered = false;

    public static function registerAfterOutdatedStyleShow(CommandEvent $event, ?Composer $composer, IOInterface $io): void
    {
        if (self::$shutdownRegistered || $composer === null) {
            return;
        }
        if ($event->getName() !== PluginEvents::COMMAND || $event->getCommandName() !== 'show') {
            return;
        }
        $input = $event->getInput();
        if (!$input->hasOption('latest') || !$input->hasOption('outdated')) {
            return;
        }
        if (!$input->getOption('latest') || !$input->getOption('outdated')) {
            return;
        }
        self::$shutdownRegistered = true;
        $output = $event->getOutput();
        $composerJson = $composer->getConfig()->getConfigSource()->getName();
        $projectRoot = dirname($composerJson);
        $formatRaw = $input->getOption('format');
        $isJson = is_string($formatRaw) && $formatRaw === 'json';

        register_shutdown_function(static function () use ($projectRoot, $output, $io, $isJson): void {
            if (getenv('COMPOSER_AGENT_SKILLS') === '0') {
                return;
            }
            try {
                $checker = new DirectSkillsOutdatedChecker();
                $rows = $checker->collectOutdated($projectRoot, null);
            } catch (\Throwable $e) {
                $io->writeError(sprintf('<comment>Direct skills outdated check failed: %s</comment>', $e->getMessage()));

                return;
            }
            if ($rows === []) {
                return;
            }
            if ($isJson) {
                $io->writeError(sprintf(
                    '<comment>Note: %d direct agent skill(s) are behind remote or local changes. JSON from this command only lists Composer packages; run `composer skills:outdated -f json` for machine-readable skill rows.</comment>',
                    count($rows),
                ));

                return;
            }
            $output->writeln('');
            $output->writeln('<comment>Direct agent skills (extra.ai-agent-skills)</comment>');
            foreach ($rows as $r) {
                $ref = $r['ref'] !== null && $r['ref'] !== '' ? sprintf(' <comment>%s</comment>', $r['ref']) : '';
                $output->writeln(sprintf(
                    '  <fg=yellow>!</> <info>%s</info>%s  %s → %s',
                    $r['name'],
                    $ref,
                    self::shortDisplay($r['installed']),
                    self::shortDisplay($r['latest']),
                ));
            }
            $output->writeln('<comment>Apply:</comment> <info>composer update</info> or <info>composer skills:update</info>  <comment>Inspect:</comment> <info>composer skills:outdated</info>');
        });
    }

    private static function shortDisplay(string $v): string
    {
        if (str_starts_with($v, 'sha256:')) {
            return strlen($v) > 20 ? substr($v, 0, 16) . '…' : $v;
        }
        if (preg_match('/^[0-9a-f]{7,40}$/i', $v)) {
            return strlen($v) > 12 ? substr($v, 0, 7) . '…' : $v;
        }

        return strlen($v) > 24 ? substr($v, 0, 21) . '…' : $v;
    }
}
