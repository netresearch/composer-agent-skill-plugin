<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Commands;

use Composer\Command\BaseCommand;
use Composer\IO\ConsoleIO;
use Netresearch\ComposerAgentSkillPlugin\Discovery\DirectInstalledSkillDiscovery;
use Netresearch\ComposerAgentSkillPlugin\Package\InstalledVersionsProvider;
use Netresearch\ComposerAgentSkillPlugin\SkillDiscovery;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use Netresearch\ComposerAgentSkillPlugin\Trust\TrustState;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ListSkillsCommand extends BaseCommand
{
    use CommandContextTrait;

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

        [$composerJsonPath, $rootName] = $this->resolveContext();
        $trust = SkillTrustManager::forComposerJson($io, $composerJsonPath, $rootName);
        $discovery = $this->discovery ?? new SkillDiscovery($io, new InstalledVersionsProvider(), $trust);

        $skills = $discovery->discoverAllSkills();
        $skills = array_merge(
            $skills,
            (new DirectInstalledSkillDiscovery())->discoverInstalled($io, $trust, dirname($composerJsonPath)),
        );
        $skills = $this->dedupeSkillsByName($skills);

        if ($skills === []) {
            $output->writeln('');
            $output->writeln(' <fg=yellow>[WARNING]</> No AI agent skills found (Composer packages or direct installs).');
            $output->writeln('');
            $output->writeln(' <fg=cyan>!</> <fg=cyan>[NOTE]</> Use extra.ai-agent-skill packages or `composer skills add` for direct sources.');
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
            $state = $skill['trust_state'];
            if ($state === TrustState::Pending) {
                $pending++;
            }
            $output->writeln(sprintf(
                '  %-' . $maxNameLength . 's  %-' . $maxPackageLength . 's  %-10s  %s',
                $skill['name'],
                $skill['package'],
                $skill['version'],
                $state->tag(),
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

    /**
     * @param list<array{name: string, description: string, location: string, package: string, version: string, file: string, trust_state: TrustState}> $skills
     * @return list<array{name: string, description: string, location: string, package: string, version: string, file: string, trust_state: TrustState}>
     */
    private function dedupeSkillsByName(array $skills): array
    {
        $byName = [];
        foreach ($skills as $s) {
            if (!isset($byName[$s['name']])) {
                $byName[$s['name']] = $s;
            }
        }

        return array_values($byName);
    }
}
