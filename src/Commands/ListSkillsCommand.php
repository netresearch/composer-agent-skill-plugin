<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Commands;

use Composer\IO\ConsoleIO;
use Netresearch\ComposerAgentSkillPlugin\SkillDiscovery;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ListSkillsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('list-skills')
            ->setDescription('List all available AI agent skills');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create ConsoleIO for SkillDiscovery
        $helperSet = $this->getHelperSet() ?? new HelperSet([new QuestionHelper()]);
        $io = new ConsoleIO($input, $output, $helperSet);

        // Discover skills
        $discovery = new SkillDiscovery($io);
        $skills = $discovery->discoverAllSkills();

        if (empty($skills)) {
            $output->writeln('');
            $output->writeln(' <fg=yellow>[WARNING]</> No AI agent skills found in installed packages.');
            $output->writeln('');
            $output->writeln(' <fg=cyan>!</> <fg=cyan>[NOTE]</> Install packages with type "ai-agent-skill" to use skills.');
            $output->writeln('');
            return self::SUCCESS;
        }

        // Sort skills alphabetically by name
        usort($skills, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        // Display header
        $output->writeln('');
        $output->writeln('Available AI Agent Skills:');
        $output->writeln('');

        // Calculate column widths
        $maxNameLength = max(array_map(fn (array $skill): int => strlen($skill['name']), $skills));
        $maxPackageLength = max(array_map(fn (array $skill): int => strlen($skill['package']), $skills));

        // Display skills in columnar format
        foreach ($skills as $skill) {
            $output->writeln(sprintf(
                '  %-' . $maxNameLength . 's  %-' . $maxPackageLength . 's  %s',
                $skill['name'],
                $skill['package'],
                $skill['version']
            ));
        }

        // Display summary
        $output->writeln('');
        $skillCount = count($skills);
        $output->writeln(sprintf(
            '%d skill%s available. Use \'composer read-skill <name>\' for details.',
            $skillCount,
            $skillCount === 1 ? '' : 's'
        ));
        $output->writeln('');

        return self::SUCCESS;
    }
}
