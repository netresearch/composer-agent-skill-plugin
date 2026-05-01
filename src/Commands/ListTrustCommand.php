<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Commands;

use Composer\Command\BaseCommand;
use Composer\IO\ConsoleIO;
use Netresearch\ComposerAgentSkillPlugin\Trust\SkillTrustManager;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists every persisted trust decision in the project's composer.json.
 *
 * Read-only — never prompts, never mutates. Companion to `composer skills:trust`.
 */
final class ListTrustCommand extends BaseCommand
{
    use CommandContextTrait;

    protected function configure(): void
    {
        $this
            ->setName('skills:list-trust')
            ->setDescription('List the persisted trust decisions for skill packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helperSet = $this->getHelperSet() ?? new HelperSet([new QuestionHelper()]);
        $io = new ConsoleIO($input, $output, $helperSet);

        [$composerJsonPath, $rootName] = $this->resolveContext();
        $trust = SkillTrustManager::forComposerJson($io, $composerJsonPath, $rootName);
        $rules = $trust->getRules();

        $output->writeln('');
        if ($rules === []) {
            $output->writeln(' <fg=yellow>[NOTE]</> No trust decisions stored. Decisions land here after the first per-package prompt or after running <info>composer skills:trust &lt;package&gt;</info>.');
            $output->writeln('');
            return self::SUCCESS;
        }

        $output->writeln('Persisted trust decisions (extra.ai-agent-skill.allow-skills):');
        $output->writeln('');

        $maxKey = max(array_map(strlen(...), array_keys($rules)));
        ksort($rules);
        foreach ($rules as $pattern => $allow) {
            $tag = $allow ? '<fg=green>[allowed]</>' : '<fg=red>[denied]</>';
            $kind = str_contains($pattern, '*') ? '<fg=yellow>(glob)</>' : '<fg=cyan>(exact)</>';
            $output->writeln(sprintf(
                '  %-' . $maxKey . 's  %s  %s',
                $pattern,
                $tag,
                $kind,
            ));
        }
        $output->writeln('');
        $output->writeln(sprintf(
            '%d entr%s. Edit with <info>composer skills:trust &lt;package&gt; [--deny|--revoke]</info>.',
            count($rules),
            count($rules) === 1 ? 'y' : 'ies',
        ));
        $output->writeln('');

        return self::SUCCESS;
    }
}
