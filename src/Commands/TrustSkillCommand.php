<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Commands;

use Composer\Command\BaseCommand;
use Composer\IO\ConsoleIO;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TrustSkillCommand extends BaseCommand
{
    /**
     * Composer package name with optional `*` glob in either segment.
     *
     * Mirrors Composer's package-name regex (lowercase only, slash-separated)
     * but allows `*` so users can persist patterns like `vendor/*` or
     * `vendor/skills-*`. We reject everything else so a malformed entry
     * can't break the regex matcher on subsequent reads.
     */
    private const PACKAGE_PATTERN = '#^[a-z0-9*]([_.*-]?[a-z0-9*]+)*/[a-z0-9*]([_.*-]?[a-z0-9*]+)*$#';

    protected function configure(): void
    {
        $this
            ->setName('skills:trust')
            ->setDescription('Allow, deny, or revoke trust for a package that ships AI agent skills')
            ->addArgument(
                'package',
                InputArgument::REQUIRED,
                'The Composer package name (e.g. vendor/foo) or glob pattern (e.g. vendor/*)'
            )
            ->addOption('deny', null, InputOption::VALUE_NONE, 'Persist a denial instead of an allow')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Remove the package from the trust map');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $package */
        $package = $input->getArgument('package');
        $deny = (bool) $input->getOption('deny');
        $revoke = (bool) $input->getOption('revoke');

        if ($deny && $revoke) {
            $output->writeln('<error>--deny and --revoke are mutually exclusive.</error>');
            return self::FAILURE;
        }

        if (preg_match(self::PACKAGE_PATTERN, $package) !== 1) {
            $output->writeln(sprintf(
                '<error>Invalid package name "%s". Expected lowercase vendor/name with optional `*` glob (e.g. vendor/foo, vendor/*, trusted-org/skills-*).</error>',
                $package,
            ));
            return self::FAILURE;
        }

        $helperSet = $this->getHelperSet() ?? new HelperSet([new QuestionHelper()]);
        $io = new ConsoleIO($input, $output, $helperSet);

        $composer = $this->tryComposer();
        if ($composer !== null) {
            $composerJsonPath = $composer->getConfig()->getConfigSource()->getName();
            $rootName = $composer->getPackage()->getName();
        } else {
            $cwd = getcwd();
            if ($cwd === false) {
                $output->writeln('<error>Could not determine current working directory.</error>');
                return self::FAILURE;
            }
            $composerJsonPath = $cwd . DIRECTORY_SEPARATOR . 'composer.json';
            $rootName = null;
        }
        $trust = SkillTrustManager::forComposerJson($io, $composerJsonPath, $rootName);

        if ($revoke) {
            $removed = $trust->revoke($package);
            if ($removed) {
                $output->writeln(sprintf('<info>Revoked trust decision for "%s".</info>', $package));
            } else {
                $output->writeln(sprintf('<comment>No trust decision found for "%s" — nothing to revoke.</comment>', $package));
            }
            return self::SUCCESS;
        }

        $trust->setExplicit($package, !$deny);
        $output->writeln(sprintf(
            '<info>%s "%s" in extra.ai-agent-skill.allow-skills.</info>',
            $deny ? 'Denied' : 'Allowed',
            $package,
        ));

        return self::SUCCESS;
    }
}
