<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\Command;

use Lmc\Cqrs\Bundle\Profiler\CqrsDataCollector;
use Lmc\Cqrs\Handler\ProfilerBag;
use Lmc\Cqrs\Types\CommandSenderInterface;
use Lmc\Cqrs\Types\QueryFetcherInterface;
use Lmc\Cqrs\Types\ValueObject\PrioritizedItem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DebugCqrsCommand extends Command
{
    /** @phpstan-var QueryFetcherInterface<mixed, mixed> */
    private QueryFetcherInterface $queryFetcher;
    /** @phpstan-var CommandSenderInterface<mixed, mixed> */
    private CommandSenderInterface $commandSender;
    private SymfonyStyle $io;
    private ?string $cacheProvider;
    private ?CqrsDataCollector $cqrsDataCollector;
    private bool $isExtensionHttpEnabled;
    private bool $isExtensionSolrEnabled;
    private ?ProfilerBag $profilerBag;

    /**
     * @phpstan-param QueryFetcherInterface<mixed, mixed> $queryFetcher
     * @phpstan-param CommandSenderInterface<mixed, mixed> $commandSender
     */
    public function __construct(
        QueryFetcherInterface $queryFetcher,
        CommandSenderInterface $commandSender,
        ?string $cacheProvider,
        ?CqrsDataCollector $cqrsDataCollector,
        bool $isExtensionHttpEnabled,
        bool $isExtensionSolrEnabled,
        ?ProfilerBag $profilerBag
    ) {
        $this->queryFetcher = $queryFetcher;
        $this->commandSender = $commandSender;
        $this->cacheProvider = $cacheProvider;
        $this->cqrsDataCollector = $cqrsDataCollector;
        $this->isExtensionHttpEnabled = $isExtensionHttpEnabled;
        $this->isExtensionSolrEnabled = $isExtensionSolrEnabled;
        $this->profilerBag = $profilerBag;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('debug:cqrs')
            ->setDescription('Display configured handlers, decoders, formatters and other services for a cqrs library');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Registered handlers, decoders, formatters and other services for a cqrs library');

        $this->io->table(
            ['Extension', 'Is Enabled'],
            [
                ['http', $this->isExtensionHttpEnabled ? 'Yes' : 'No'],
                ['solr', $this->isExtensionSolrEnabled ? 'Yes' : 'No'],
            ]
        );

        $this->separator();

        $this->io->title('QueryFetcherInterface');
        $this->io->definitionList(
            ['Class' => get_class($this->queryFetcher)],
            ['Cache (enabled)' => $this->queryFetcher->isCacheEnabled() ? 'Yes' : 'No'],
            ['Cache (provider)' => $this->cacheProvider ?? '-'],
        );

        $this->io->section('Registered Query handlers');
        $this->io->table(...$this->formatPrioritizedItems($this->queryFetcher->getHandlers()));

        $this->io->section('Registered Response decoders');
        $this->io->table(...$this->formatPrioritizedItems($this->queryFetcher->getDecoders()));

        $this->separator();

        $this->io->title('CommandSenderInterface');
        $this->io->definitionList(['Class' => get_class($this->commandSender)]);

        $this->io->section('Registered Send Command handlers');
        $this->io->table(...$this->formatPrioritizedItems($this->commandSender->getHandlers()));

        $this->io->section('Registered Response decoders');
        $this->io->table(...$this->formatPrioritizedItems($this->commandSender->getDecoders()));

        $this->separator();

        $this->io->title('Profiler');
        if ($this->cqrsDataCollector !== null) {
            $verbosity = 'normal';
            if ($this->profilerBag && !empty($currentVerbosity = $this->profilerBag->getVerbosity())) {
                $verbosity = $currentVerbosity;
            }

            $this->io->definitionList(
                ['Is Enabled' => 'Yes'],
                ['Verbosity' => $verbosity],
                ['Data Collector' => get_class($this->cqrsDataCollector)],
            );

            $this->io->section('Registered Profiler formatters');
            $this->io->table(...$this->formatItems($this->cqrsDataCollector->getRegisteredFormatters()));
        } else {
            $this->io->definitionList(
                ['Is Enabled' => 'No'],
            );
        }

        return 0;
    }

    /**
     * @phpstan-param iterable<PrioritizedItem<mixed>> $prioritizedItems
     * @param PrioritizedItem[] $prioritizedItems
     */
    private function formatPrioritizedItems(iterable $prioritizedItems): array
    {
        $formatted = [];
        $i = 1;

        foreach ($prioritizedItems as $item) {
            $formatted[] = [
                sprintf('#%d', $i++),
                get_class($item->getItem()),
                $item->getPriority(),
            ];
        }

        return [
            ['Order', 'Class', 'Priority'],
            $formatted,
        ];
    }

    private function formatItems(iterable $items): array
    {
        $formatted = [];
        $i = 1;

        foreach ($items as $item) {
            $formatted[] = [
                sprintf('#%d', $i++),
                get_class($item),
            ];
        }

        return [
            ['Order', 'Class'],
            $formatted,
        ];
    }

    private function separator(): void
    {
        $this->io->writeln(str_repeat('*', 120));
    }
}
