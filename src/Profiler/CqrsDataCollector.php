<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\Profiler;

use Lmc\Cqrs\Handler\ProfilerBag;
use Lmc\Cqrs\Types\CommandSenderInterface;
use Lmc\Cqrs\Types\Formatter\ProfilerFormatterInterface;
use Lmc\Cqrs\Types\QueryFetcherInterface;
use Lmc\Cqrs\Types\ValueObject\PrioritizedItem;
use Lmc\Cqrs\Types\ValueObject\ProfilerItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class CqrsDataCollector extends DataCollector
{
    /** @var ProfilerFormatterInterface[] */
    private array $formatters;

    public static function getDefaultPriority(): int
    {
        return 0;
    }

    /**
     * @phpstan-param QueryFetcherInterface<mixed, mixed> $queryFetcher
     * @phpstan-param CommandSenderInterface<mixed, mixed> $commandSender
     * @param \Traversable<ProfilerFormatterInterface> $formatters
     */
    public function __construct(
        private ProfilerBag $profilerBag,
        private QueryFetcherInterface $queryFetcher,
        private CommandSenderInterface $commandSender,
        \Traversable $formatters,
        private ?string $cacheProvider,
    ) {
        $this->formatters = iterator_to_array($formatters);
    }

    /**
     * Collects data for the given Request and Response.
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $this->data = [
            'formatters' => $this->formatters,
            'items' => $this->formatItems($this->profilerBag->getBag()),
            'queryFetcher' => [
                'class' => get_class($this->queryFetcher),
                'isCacheEnabled' => $this->queryFetcher->isCacheEnabled(),
                'cacheProvider' => $this->cacheProvider ?? '-',
                'handlers' => $this->mapPrioritizedItems($this->queryFetcher->getHandlers(), 'handler'),
                'decoders' => $this->mapPrioritizedItems($this->queryFetcher->getDecoders(), 'decoder'),
            ],
            'commandSender' => [
                'class' => get_class($this->commandSender),
                'handlers' => $this->mapPrioritizedItems($this->commandSender->getHandlers(), 'handler'),
                'decoders' => $this->mapPrioritizedItems($this->commandSender->getDecoders(), 'decoder'),
            ],
        ];
    }

    private function formatItems(array $profilerBag): array
    {
        return array_map(
            fn (ProfilerItem $item) => array_reduce(
                $this->formatters,
                fn (ProfilerItem $item, ProfilerFormatterInterface $formatter) => $formatter->formatItem($item),
                $item,
            ),
            $profilerBag,
        );
    }

    private function mapPrioritizedItems(array $prioritizedItems, string $itemKey): array
    {
        return array_map(
            fn (PrioritizedItem $item) => [
                $itemKey => get_class($item->getItem()),
                'priority' => $item->getPriority(),
            ],
            $prioritizedItems,
        );
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getRegisteredFormatters(): array
    {
        return $this->formatters;
    }

    public function getFormatters(): array
    {
        return array_map(
            fn (ProfilerFormatterInterface $formatter) => get_class($formatter),
            $this->data['formatters'] ?? [],
        );
    }

    public function getItems(): array
    {
        return array_values($this->data['items'] ?? []);
    }

    public function getQueries(): array
    {
        return array_values(array_filter(
            $this->getItems(),
            fn (ProfilerItem $item) => $item->getItemType() === ProfilerItem::TYPE_QUERY
        ));
    }

    public function getCommands(): array
    {
        return array_values(array_filter(
            $this->getItems(),
            fn (ProfilerItem $item) => $item->getItemType() === ProfilerItem::TYPE_COMMAND
        ));
    }

    public function getOthers(): array
    {
        return array_values(array_filter(
            $this->getItems(),
            fn (ProfilerItem $item) => $item->getItemType() === ProfilerItem::TYPE_OTHER
        ));
    }

    public function countCachedQueries(): int
    {
        return $this->countCachedQueryItems(true);
    }

    public function countUncachedQueries(): int
    {
        return $this->countCachedQueryItems(false);
    }

    private function countCachedQueryItems(bool $cached): int
    {
        return array_reduce(
            $this->getQueries(),
            function (int $count, ProfilerItem $item) use ($cached) {
                if ($item->isLoadedFromCache() === $cached) {
                    return $count + 1;
                }

                return $count;
            },
            0,
        );
    }

    public function getQueryFetcher(): array
    {
        return $this->data['queryFetcher'] ?? [];
    }

    public function getCommandSender(): array
    {
        return $this->data['commandSender'] ?? [];
    }

    public function getName(): string
    {
        return 'cqrs';
    }
}
