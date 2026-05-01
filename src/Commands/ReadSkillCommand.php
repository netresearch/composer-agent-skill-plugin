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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReadSkillCommand extends BaseCommand
{
    public function __construct(
        private readonly ?SkillDiscovery $discovery = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('read-skill')
            ->setDescription('Display full SKILL.md content for a specific skill')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name of the skill to read'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $skillName */
        $skillName = $input->getArgument('name');

        // Create ConsoleIO for SkillDiscovery
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

        // Find the requested skill
        $foundSkill = null;
        foreach ($skills as $skill) {
            if ($skill['name'] === $skillName) {
                $foundSkill = $skill;
                break;
            }
        }

        // Handle skill not found
        if ($foundSkill === null) {
            $output->writeln('');
            $output->writeln(sprintf('<error>Error: Skill \'%s\' not found</error>', $skillName));
            $output->writeln('');

            if (!empty($skills)) {
                $output->writeln('Available skills:');
                // Sort alphabetically for display
                usort($skills, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
                foreach ($skills as $skill) {
                    $output->writeln(sprintf('  - %s (%s)', $skill['name'], $skill['package']));
                }
            }
            $output->writeln('');

            return self::FAILURE;
        }

        // Display skill header
        $skillFilePath = $foundSkill['location'] . '/' . $foundSkill['file'];
        $output->writeln('');
        $output->writeln(sprintf('Reading: %s', $foundSkill['name']));
        $output->writeln(sprintf('Package: %s v%s', $foundSkill['package'], $foundSkill['version']));
        $output->writeln(sprintf('Base Directory: %s', $foundSkill['location']));

        $trustState = $foundSkill['trust_state'];
        if ($trustState === 'pending') {
            $output->writeln('<comment>Trust:   pending — this skill is NOT registered in AGENTS.md.</comment>');
            $output->writeln(sprintf('<comment>         Run `composer install` interactively or pre-authorize: composer config --json --merge extra.ai-agent-skill.allow-skills \'{"%s": true}\'</comment>', $foundSkill['package']));
        } elseif ($trustState === 'denied') {
            $output->writeln('<comment>Trust:   denied — this skill is explicitly blocked from AGENTS.md.</comment>');
        }
        $output->writeln('');

        // Read and display full SKILL.md content
        $content = file_get_contents($skillFilePath);
        if ($content === false) {
            $output->writeln(sprintf('<error>Error: Could not read file at %s</error>', $skillFilePath));
            return self::FAILURE;
        }

        $output->write($content);

        // Ensure content ends with newline
        if (!str_ends_with($content, "\n")) {
            $output->writeln('');
        }

        // Display working directory reminder (helps AI agents use correct path for scripts)
        $output->writeln('');
        $output->writeln('---');
        $output->writeln(sprintf('<info>ℹ️  Working Directory: %s</info>', $foundSkill['location']));
        $output->writeln(sprintf('<comment>    cd "%s" && your-command</comment>', $foundSkill['location']));
        $output->writeln('---');

        // Display footer
        $output->writeln('');
        $output->writeln(sprintf('Skill read: %s', $foundSkill['name']));
        $output->writeln('');

        return self::SUCCESS;
    }
}
