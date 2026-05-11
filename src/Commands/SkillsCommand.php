<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Commands;

use Composer\Command\BaseCommand;
use Composer\IO\ConsoleIO;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\DirectSkillsCoordinator;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\DirectSkillsException;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\MissingSkillsLockException;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\Exception\StaleSkillsLockException;
use Netresearch\ComposerAgentSkillPlugin\DirectSkills\SourceEntry;
use Netresearch\ComposerAgentSkillPlugin\Util\ComposerJsonDirectSkillsWriter;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dispatcher: `composer skills …` and `composer skills:*` aliases.
 */
final class SkillsCommand extends BaseCommand
{
    use CommandContextTrait;

    /** @param ?string $fixedSubcommand When set, command name is skills:{$fixedSubcommand} (no nested arg). */
    public function __construct(
        private readonly ?string $fixedSubcommand = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        if ($this->fixedSubcommand !== null) {
            $this->setName('skills:' . $this->fixedSubcommand)
                ->setDescription($this->describeFixed($this->fixedSubcommand));
            $this->configureArgumentsFor($this->fixedSubcommand);
        } else {
            $this->setName('skills')
                ->setDescription('Manage directly installed AI agent skills (non-Composer packages)')
                ->addArgument('subcommand', InputArgument::OPTIONAL, 'add|install|update|remove|list', 'list')
                ->addArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Arguments for the subcommand');
            $this->configureSharedOptions();
        }
    }

    private function describeFixed(string $sub): string
    {
        return match ($sub) {
            'add' => 'Add a direct skill source to composer.json and run update',
            'install' => 'Install direct skills from composer.skills.lock',
            'update' => 'Resolve sources and refresh composer.skills.lock',
            'remove' => 'Remove a direct skill or source from composer.json',
            'list' => 'List configured direct skill sources',
            default => 'Direct skills helper',
        };
    }

    private function configureArgumentsFor(string $sub): void
    {
        $this->configureSharedOptions();
        match ($sub) {
            'add' => $this
                ->addArgument('source', InputArgument::REQUIRED, 'GitHub HTTPS/SSH, owner/repo shorthand, GitHub tree URL, or local path (generic non-GitHub git remotes are not supported)'),
            'remove' => $this
                ->addArgument('name', InputArgument::REQUIRED, 'Skill name or source name'),
            default => null,
        };
    }

    private function configureSharedOptions(): void
    {
        $this->addOption('skill', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Skill id (repeat or use *)', []);
        $this->addOption('ref', null, InputOption::VALUE_REQUIRED, 'Git branch / tag / commit');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Override source id');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print intent without writing');
        $this->addOption('source', null, InputOption::VALUE_NONE, 'With remove: argument is a source id (drop entire source)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helperSet = $this->getHelperSet() ?? new HelperSet([new QuestionHelper()]);
        $io = new ConsoleIO($input, $output, $helperSet);

        if ($this->fixedSubcommand !== null) {
            $sub = $this->fixedSubcommand;
        } else {
            $rawSub = $input->getArgument('subcommand');
            $sub = is_string($rawSub) ? $rawSub : 'list';
        }
        $sub = $sub === '' ? 'list' : $sub;

        [$composerJsonPath, $rootName] = $this->resolveContext();
        unset($rootName);
        $projectRoot = dirname($composerJsonPath);

        $coord = new DirectSkillsCoordinator();

        try {
            return match ($sub) {
                'add' => $this->runAdd($io, $input, $output, $projectRoot, $composerJsonPath, $coord),
                'install' => $this->runInstall($io, $projectRoot, $coord),
                'update' => $this->runUpdate($io, $projectRoot, $coord),
                'remove' => $this->runRemove($io, $input, $output, $projectRoot, $composerJsonPath, $coord),
                'list' => $this->runList($output, $composerJsonPath),
                default => $this->badSubcommand($output, $sub),
            };
        } catch (MissingSkillsLockException | StaleSkillsLockException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return 2;
        } catch (DirectSkillsException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return 1;
        }
    }

    private function badSubcommand(OutputInterface $output, string $sub): int
    {
        $output->writeln(sprintf('<error>Unknown skills subcommand "%s". Try: add, install, update, remove, list.</error>', $sub));

        return 7;
    }

    private function runAdd(
        ConsoleIO $io,
        InputInterface $input,
        OutputInterface $output,
        string $projectRoot,
        string $composerJsonPath,
        DirectSkillsCoordinator $coord,
    ): int {
        if ($this->fixedSubcommand !== null) {
            $rawSrc = $input->getArgument('source');
            $src = is_string($rawSrc) ? $rawSrc : '';
        } else {
            $argsRaw = $input->getArgument('args');
            $argsList = is_array($argsRaw) ? $argsRaw : [];
            $first = $argsList[0] ?? null;
            $src = is_string($first) ? $first : '';
        }
        if ($src === '') {
            $output->writeln('<error>Missing source argument.</error>');

            return 7;
        }

        /** @var list<string> $skillsOpt */
        $skillsOpt = array_values(array_filter((array) $input->getOption('skill'), static fn ($v) => $v !== null && $v !== ''));
        if ($skillsOpt === []) {
            $output->writeln('<error>Provide at least one --skill (skill id) or --skill=* for all.</error>');

            return 7;
        }

        $ref = $input->getOption('ref');
        $ref = is_string($ref) ? $ref : null;
        $nameOv = $input->getOption('name');
        $nameOv = is_string($nameOv) ? $nameOv : null;

        $resolved = $coord->getResolver()->resolve($src, $nameOv, $ref);

        $skillsList = $skillsOpt;
        if (in_array('*', $skillsList, true)) {
            $skillsList = ['*'];
        }

        $pathStored = $resolved->path;
        if ($resolved->type === 'path' && $pathStored !== null && $pathStored !== '') {
            $pathStored = $this->relativePathFromProjectRoot($projectRoot, $pathStored);
        }

        $type = $resolved->type === 'path' ? 'path' : 'github';
        $entry = $type === 'path'
            ? new SourceEntry(
                $resolved->name,
                'path',
                null,
                null,
                $pathStored,
                $skillsList,
                'copy',
            )
            : new SourceEntry(
                $resolved->name,
                $type,
                $resolved->url,
                $resolved->ref,
                $resolved->path,
                $skillsList,
                'copy',
            );

        if ($input->getOption('dry-run')) {
            $output->writeln('<info>Would upsert source:</info>');
            $encoded = json_encode($entry->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $output->writeln(is_string($encoded) ? $encoded : '{}');

            return 0;
        }

        (new ComposerJsonDirectSkillsWriter())->upsertSource($composerJsonPath, $entry);
        $output->writeln('<info>Updated composer.json; resolving skills…</info>');
        $coord->updateFloating($io, $projectRoot);
        $output->writeln('<info>Done.</info>');

        return 0;
    }

    private function runInstall(ConsoleIO $io, string $projectRoot, DirectSkillsCoordinator $coord): int
    {
        $coord->installPinned($io, $projectRoot);
        $io->write('<info>Direct skills installed from lock.</info>');

        return 0;
    }

    private function runUpdate(ConsoleIO $io, string $projectRoot, DirectSkillsCoordinator $coord): int
    {
        $coord->updateFloating($io, $projectRoot);
        $io->write('<info>composer.skills.lock refreshed and skills materialized.</info>');

        return 0;
    }

    private function runRemove(
        ConsoleIO $io,
        InputInterface $input,
        OutputInterface $output,
        string $projectRoot,
        string $composerJsonPath,
        DirectSkillsCoordinator $coord,
    ): int {
        if ($this->fixedSubcommand !== null) {
            $rawT = $input->getArgument('name');
            $target = is_string($rawT) ? $rawT : '';
        } else {
            $argsRaw = $input->getArgument('args');
            $argsList = is_array($argsRaw) ? $argsRaw : [];
            $first = $argsList[0] ?? null;
            $target = is_string($first) ? $first : '';
        }
        if ($target === '') {
            $output->writeln('<error>Missing skill or source name.</error>');

            return 7;
        }
        $whole = (bool) $input->getOption('source');
        if ($input->getOption('dry-run')) {
            $output->writeln($whole
                ? "<info>Would remove source {$target}</info>"
                : "<info>Would remove skill {$target} from sources</info>");

            return 0;
        }
        (new ComposerJsonDirectSkillsWriter())->removeSkillOrSource($composerJsonPath, $target, $whole);
        $output->writeln('<info>Updated composer.json; refreshing lock…</info>');
        $coord->updateFloating($io, $projectRoot);
        $output->writeln('<info>Done.</info>');

        return 0;
    }

    private function runList(OutputInterface $output, string $composerJsonPath): int
    {
        $data = \Netresearch\ComposerAgentSkillPlugin\Util\ComposerJsonReader::read($composerJsonPath);
        if ($data === null) {
            $output->writeln('<error>Cannot read composer.json.</error>');

            return 1;
        }
        $extra = $data['extra'] ?? [];
        $extra = is_array($extra) ? $extra : [];
        $block = $extra['ai-agent-skills'] ?? null;
        if (!is_array($block) || !isset($block['sources']) || !is_array($block['sources'])) {
            $output->writeln('No extra.ai-agent-skills.sources configured.');

            return 0;
        }
        $output->writeln('<info>Direct skill sources:</info>');
        foreach ($block['sources'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = isset($row['name']) && is_scalar($row['name']) ? (string) $row['name'] : '?';
            $type = isset($row['type']) && is_scalar($row['type']) ? (string) $row['type'] : '?';
            $output->writeln(sprintf('  - %s (%s)', $name, $type));
        }

        return 0;
    }

    private function relativePathFromProjectRoot(string $projectRoot, string $absoluteOrRelative): string
    {
        $root = realpath($projectRoot);
        if ($root === false) {
            return str_replace(DIRECTORY_SEPARATOR, '/', $absoluteOrRelative);
        }
        $path = realpath($absoluteOrRelative);
        if ($path === false) {
            return str_replace(DIRECTORY_SEPARATOR, '/', $absoluteOrRelative);
        }
        if (str_starts_with($path, $root)) {
            $rel = substr($path, strlen($root));
            $rel = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $rel), '/');

            return $rel === '' ? '.' : './' . $rel;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $absoluteOrRelative);
    }
}
