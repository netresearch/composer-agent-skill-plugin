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

        $rootDir = getcwd();
        if ($rootDir === false) {
            $output->writeln('<error>Could not determine current working directory.</error>');
            return self::FAILURE;
        }

        $helperSet = $this->getHelperSet() ?? new HelperSet([new QuestionHelper()]);
        $io = new ConsoleIO($input, $output, $helperSet);
        $trust = new SkillTrustManager($io, $rootDir);

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
