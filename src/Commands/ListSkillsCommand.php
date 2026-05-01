<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Commands;

use Composer\Command\BaseCommand;
use Composer\IO\ConsoleIO;
use Netresearch\ComposerAgentSkillPlugin\Package\InstalledVersionsProvider;
use Netresearch\ComposerAgentSkillPlugin\SkillDiscovery;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ListSkillsCommand extends BaseCommand
{
    public function __construct(
        private readonly ?SkillDiscovery $discovery = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('list-skills')
            ->setDescription('List all available AI agent skills');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helperSet = $this->getHelperSet() ?? new HelperSet([new QuestionHelper()]);
        $io = new ConsoleIO($input, $output, $helperSet);

        $discovery = $this->discovery;
        if ($discovery === null) {
            $rootDir = getcwd();
            if ($rootDir === false) {
                $rootDir = sys_get_temp_dir();
            }
            $trust = new SkillTrustManager($io, $rootDir);
            $discovery = new SkillDiscovery($io, new InstalledVersionsProvider(), $trust);
        }

        $skills = $discovery->discoverAllSkills();

        if ($skills === []) {
            $output->writeln('');
            $output->writeln(' <fg=yellow>[WARNING]</> No AI agent skills found in installed packages.');
            $output->writeln('');
            $output->writeln(' <fg=cyan>!</> <fg=cyan>[NOTE]</> Install packages declaring extra.ai-agent-skill to register skills.');
            $output->writeln('');
            return self::SUCCESS;
        }

        usort($skills, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $output->writeln('');
        $output->writeln('Available AI Agent Skills:');
        $output->writeln('');

        $maxNameLength = max(array_map(fn (array $s): int => strlen($s['name']), $skills));
        $maxPackageLength = max(array_map(fn (array $s): int => strlen($s['package']), $skills));

        $pending = 0;
        foreach ($skills as $skill) {
            $state = $skill['trust_state'] ?? 'allowed';
            $tag = match ($state) {
                'allowed' => '<fg=green>[allowed]</>',
                'denied'  => '<fg=red>[denied]</>',
                default   => '<fg=yellow>[pending]</>',
            };
            if ($state === 'pending') {
                $pending++;
            }
            $output->writeln(sprintf(
                '  %-' . $maxNameLength . 's  %-' . $maxPackageLength . 's  %-10s  %s',
                $skill['name'],
                $skill['package'],
                $skill['version'],
                $tag,
            ));
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '%d skill%s available. Use \'composer read-skill <name>\' for details.',
            count($skills),
            count($skills) === 1 ? '' : 's',
        ));
        if ($pending > 0) {
            $output->writeln(sprintf(
                '<comment>%d pending — run `composer install` interactively to be prompted.</comment>',
                $pending,
            ));
        }
        $output->writeln('');

        return self::SUCCESS;
    }
}
