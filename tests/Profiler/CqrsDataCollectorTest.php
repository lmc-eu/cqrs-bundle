<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\Profiler;

use Lmc\Cqrs\Bundle\AbstractTestCase;
use Lmc\Cqrs\Bundle\Service\ErrorProfilerFormatter;
use Lmc\Cqrs\Handler\CommandSender;
use Lmc\Cqrs\Handler\Handler\CallbackQueryHandler;
use Lmc\Cqrs\Handler\Handler\CallbackSendCommandHandler;
use Lmc\Cqrs\Handler\ProfilerBag;
use Lmc\Cqrs\Handler\QueryFetcher;
use Lmc\Cqrs\Types\CommandSenderInterface;
use Lmc\Cqrs\Types\Decoder\JsonResponseDecoder;
use Lmc\Cqrs\Types\Formatter\JsonProfilerFormatter;
use Lmc\Cqrs\Types\QueryFetcherInterface;
use Lmc\Cqrs\Types\ValueObject\CacheKey;
use Lmc\Cqrs\Types\ValueObject\FormattedValue;
use Lmc\Cqrs\Types\ValueObject\ProfilerItem;
use Ramsey\Uuid\Uuid;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CqrsDataCollectorTest extends AbstractTestCase
{
    /** @phpstan-var QueryFetcherInterface<mixed, mixed> */
    private QueryFetcherInterface $queryFetcher;
    /** @phpstan-var CommandSenderInterface<mixed, mixed> */
    private CommandSenderInterface $commandSender;

    protected function setUp(): void
    {
        $this->queryFetcher = new QueryFetcher(false, null, null);
        $this->commandSender = new CommandSender(null);
    }

    /**
     * @test
     */
    public function shouldGetDefaultFormattersPriority(): void
    {
        $this->assertSame(0, CqrsDataCollector::getDefaultPriority());
    }

    private function setUpCollectorWithData(
        iterable $queries,
        iterable $commands,
        iterable $others,
        iterable $formatters
    ): CqrsDataCollector {
        $this->queryFetcher->addHandler(new CallbackQueryHandler(), 50);
        $this->queryFetcher->addDecoder(new JsonResponseDecoder(), 50);

        $this->commandSender->addHandler(new CallbackSendCommandHandler(), 50);
        $this->commandSender->addDecoder(new JsonResponseDecoder(), 50);

        $profilerBag = new ProfilerBag();

        foreach ($queries as $query) {
            $profilerBag->add(Uuid::uuid4(), $query);
        }
        foreach ($commands as $command) {
            $profilerBag->add(Uuid::uuid4(), $command);
        }
        foreach ($others as $other) {
            $profilerBag->add(Uuid::uuid4(), $other);
        }

        return new CqrsDataCollector(
            $profilerBag,
            $this->queryFetcher,
            $this->commandSender,
            (function () use ($formatters): \Generator {
                yield from $formatters;
            })(),
            '@cache.provider'
        );
    }

    /**
     * @test
     */
    public function shouldCollectsQueriesAndCommands(): CqrsDataCollector
    {
        $queries = [
            new ProfilerItem('q1', null, ProfilerItem::TYPE_QUERY, 'test'),
            new ProfilerItem('q2', null, ProfilerItem::TYPE_QUERY, 'test'),
        ];

        $commands = [
            new ProfilerItem('c1', null, ProfilerItem::TYPE_COMMAND, 'test'),
            new ProfilerItem('c2', null, ProfilerItem::TYPE_COMMAND, 'test'),
        ];

        $others = [
            new ProfilerItem('o1', null, 'foo', 'test'),
            new ProfilerItem('o2', null, 'bar', 'test'),
        ];

        $collector = $this->setUpCollectorWithData($queries, $commands, $others, []);

        $this->assertSame('cqrs', $collector->getName());
        $this->assertEmpty($collector->getItems());
        $this->assertEmpty($collector->getQueries());
        $this->assertEmpty($collector->getCommands());
        $this->assertEmpty($collector->getOthers());
        $this->assertEmpty($collector->getFormatters());
        $this->assertEmpty($collector->getCommandSender());
        $this->assertEmpty($collector->getQueryFetcher());
        $this->assertEmpty($collector->getRegisteredFormatters());

        $collector->collect(new Request(), new Response());

        $this->assertSame(array_values([...$queries, ...$commands, ...$others]), $collector->getItems());
        $this->assertSame($queries, $collector->getQueries());
        $this->assertSame($commands, $collector->getCommands());
        $this->assertSame($others, $collector->getOthers());
        $this->assertSame([], $collector->getFormatters());

        $this->assertEquals(
            [
                'class' => get_class($this->commandSender),
                'handlers' => [
                    [
                        'handler' => CallbackSendCommandHandler::class,
                        'priority' => 50,
                    ],
                ],
                'decoders' => [
                    [
                        'decoder' => JsonResponseDecoder::class,
                        'priority' => 50,
                    ],
                ],
            ],
            $collector->getCommandSender()
        );

        $this->assertSame(
            [
                'class' => get_class($this->queryFetcher),
                'isCacheEnabled' => false,
                'cacheProvider' => '@cache.provider',
                'handlers' => [
                    [
                        'handler' => CallbackQueryHandler::class,
                        'priority' => 50,
                    ],
                ],
                'decoders' => [
                    [
                        'decoder' => JsonResponseDecoder::class,
                        'priority' => 50,
                    ],
                ],
            ],
            $collector->getQueryFetcher()
        );

        $this->assertSame([], $collector->getRegisteredFormatters());

        return $collector;
    }

    /**
     * @depends shouldCollectsQueriesAndCommands
     *
     * @test
     */
    public function shouldResetCollectedQueries(CqrsDataCollector $collector): void
    {
        $collector->reset();

        $this->assertEmpty($collector->getItems());
        $this->assertEmpty($collector->getQueries());
        $this->assertEmpty($collector->getCommands());
        $this->assertEmpty($collector->getOthers());
        $this->assertEmpty($collector->getFormatters());
        $this->assertEmpty($collector->getCommandSender());
        $this->assertEmpty($collector->getQueryFetcher());
        $this->assertEmpty($collector->getRegisteredFormatters());
    }

    /**
     * @test
     */
    public function countsCachedAndUncachedQueries(): void
    {
        $queries = [
            new ProfilerItem('q1', null, ProfilerItem::TYPE_QUERY, 'test'),
            new ProfilerItem('q2', null, ProfilerItem::TYPE_QUERY, 'test'),
            new ProfilerItem(
                'q3-cached',
                null,
                ProfilerItem::TYPE_QUERY,
                'test',
                'response',
                null,
                new CacheKey('q3-key'),
                false,
                true,
                100
            ),
            new ProfilerItem(
                'q3-from-cache',
                null,
                ProfilerItem::TYPE_QUERY,
                'test',
                'response',
                null,
                new CacheKey('q3-key'),
                true,
                false
            ),
        ];

        $commands = [
            new ProfilerItem('c1', null, ProfilerItem::TYPE_COMMAND, 'test'),
            new ProfilerItem('c2', null, ProfilerItem::TYPE_COMMAND, 'test'),
        ];

        $others = [
            new ProfilerItem('o1', null, 'foo', 'test'),
            new ProfilerItem('o2', null, 'bar', 'test'),
        ];

        $collector = $this->setUpCollectorWithData($queries, $commands, $others, []);

        $collector->collect(new Request(), new Response());

        $this->assertCount(4, $collector->getQueries());
        $this->assertSame(1, $collector->countCachedQueries());
        $this->assertSame(1, $collector->countUncachedQueries());
    }

    /**
     * @test
     */
    public function shouldFormatCollectedItems(): void
    {
        $error = new \Exception('error message');

        $queries = [
            new ProfilerItem(
                'query',
                [
                    'body' => '{"body": "value"}',
                ],
                ProfilerItem::TYPE_QUERY,
                'test',
                '{"data": {"response": "value"}}',
                $error
            ),
        ];

        $expected = [
            new ProfilerItem(
                'query',
                [
                    'body' => new FormattedValue(
                        '{"body": "value"}',
                        ['body' => 'value']
                    ),
                ],
                ProfilerItem::TYPE_QUERY,
                'test',
                new FormattedValue(
                    '{"data": {"response": "value"}}',
                    ['data' => ['response' => 'value']]
                ),
                new FormattedValue(
                    'error message',
                    FlattenException::createFromThrowable($error),
                    true
                )
            ),
        ];

        $collector = $this->setUpCollectorWithData($queries, [], [], [
            new JsonProfilerFormatter(),
            new ErrorProfilerFormatter(),
        ]);

        $collector->collect(new Request(), new Response());

        $this->assertEquals($expected, $collector->getQueries());
        $this->assertEquals(
            [
                JsonProfilerFormatter::class,
                ErrorProfilerFormatter::class,
            ],
            $collector->getFormatters()
        );
        $this->assertEquals(
            [
                new JsonProfilerFormatter(),
                new ErrorProfilerFormatter(),
            ],
            $collector->getRegisteredFormatters()
        );
    }
}
