<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\DependencyInjection;

use Lmc\Cqrs\Bundle\AbstractTestCase;
use Lmc\Cqrs\Bundle\Command\DebugCqrsCommand;
use Lmc\Cqrs\Bundle\Controller\CacheController;
use Lmc\Cqrs\Bundle\Profiler\CqrsDataCollector;
use Lmc\Cqrs\Bundle\Service\ErrorProfilerFormatter;
use Lmc\Cqrs\Handler\CommandSender;
use Lmc\Cqrs\Handler\Handler\CallbackQueryHandler;
use Lmc\Cqrs\Handler\Handler\CallbackSendCommandHandler;
use Lmc\Cqrs\Handler\ProfilerBag;
use Lmc\Cqrs\Handler\QueryFetcher;
use Lmc\Cqrs\Http\Decoder\HttpMessageResponseDecoder;
use Lmc\Cqrs\Http\Decoder\StreamResponseDecoder;
use Lmc\Cqrs\Http\Formatter\HttpProfilerFormatter;
use Lmc\Cqrs\Http\Handler\HttpQueryHandler;
use Lmc\Cqrs\Http\Handler\HttpSendCommandHandler;
use Lmc\Cqrs\Solr\Handler\SolrQueryHandler;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\ApplicatorFactory;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\ApplicatorInterface;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\EntityApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\FacetsApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\FilterApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\FiltersApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\FulltextApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\FulltextBigramApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\FulltextBoostApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\GroupingApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\GroupingFacetApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\ParameterizedApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\SortApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\Applicator\StatsApplicator;
use Lmc\Cqrs\Solr\QueryBuilder\QueryBuilder;
use Lmc\Cqrs\Types\CommandSenderInterface;
use Lmc\Cqrs\Types\Decoder\JsonResponseDecoder;
use Lmc\Cqrs\Types\Decoder\ResponseDecoderInterface;
use Lmc\Cqrs\Types\Formatter\JsonProfilerFormatter;
use Lmc\Cqrs\Types\Formatter\ProfilerFormatterInterface;
use Lmc\Cqrs\Types\QueryFetcherInterface;
use Lmc\Cqrs\Types\QueryHandlerInterface;
use Lmc\Cqrs\Types\SendCommandHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LmcCqrsExtensionTest extends AbstractTestCase
{
    private LmcCqrsExtension $extension;
    private ContainerBuilder $containerBuilder;

    protected function setUp(): void
    {
        $this->extension = new LmcCqrsExtension();

        $this->containerBuilder = new ContainerBuilder();
        $this->containerBuilder->registerExtension($this->extension);
    }

    /**
     * @test
     */
    public function shouldSetUpDefaultServices(): void
    {
        $configs = [];

        $this->extension->load($configs, $this->containerBuilder);

        $this->assertDefaultSettings($this->containerBuilder);
        $this->assertNoCacheSettings($this->containerBuilder);
        $this->assertNoProfilerSettings($this->containerBuilder);
        $this->assertNoDebugSettings($this->containerBuilder);
        $this->assertNoHttpExtensionSettings($this->containerBuilder);
        $this->assertNoSolrExtensionSettings($this->containerBuilder);
    }

    private function assertDefaultSettings(ContainerBuilder $containerBuilder): void
    {
        $this->assertQueryFetcher($containerBuilder);
        $this->assertCommandSender($containerBuilder);

        $this->assertHasServiceWithAlias(
            'lmc_cqrs.query_handler.callback',
            CallbackQueryHandler::class,
            $containerBuilder
        );
        $this->assertHasServiceWithAlias(
            'lmc_cqrs.send_command_handler.callback',
            CallbackSendCommandHandler::class,
            $containerBuilder
        );

        $this->assertHasServiceWithAliasTagAndPriority(
            'lmc_cqrs.response_decoder.json',
            JsonResponseDecoder::class,
            'lmc_cqrs.response_decoder',
            60,
            $containerBuilder
        );

        $expectedAutoconfiguredTags = [
            'lmc_cqrs.query_handler' => QueryHandlerInterface::class,
            'lmc_cqrs.send_command_handler' => SendCommandHandlerInterface::class,
            'lmc_cqrs.profiler_formatter' => ProfilerFormatterInterface::class,
            'lmc_cqrs.response_decoder' => ResponseDecoderInterface::class,
        ];

        $this->assertAutoconfiguredTags($expectedAutoconfiguredTags, $containerBuilder);
    }

    private function assertNoCacheSettings(ContainerBuilder $containerBuilder): void
    {
        $this->assertFalse($containerBuilder->getParameter('lmc_cqrs.cache.enabled'));
        $this->assertNull($containerBuilder->getParameter('lmc_cqrs.cache.provider'));

        $this->assertFalse($containerBuilder->has('lmc_cqrs.cache_provider'));
    }

    private function assertNoProfilerSettings(ContainerBuilder $containerBuilder): void
    {
        $this->assertFalse($containerBuilder->has(CacheController::class));
        $this->assertFalse($containerBuilder->has(CqrsDataCollector::class));

        $this->assertFalse($containerBuilder->has('lmc_cqrs.profiler_bag'));
        $this->assertFalse($containerBuilder->has(ProfilerBag::class));

        $this->assertFalse($containerBuilder->has(JsonProfilerFormatter::class));
        $this->assertFalse($containerBuilder->has(ErrorProfilerFormatter::class));
    }

    private function assertNoDebugSettings(ContainerBuilder $containerBuilder): void
    {
        $this->assertFalse($containerBuilder->has(DebugCqrsCommand::class));
    }

    private function assertNoHttpExtensionSettings(ContainerBuilder $containerBuilder): void
    {
        $this->assertFalse($containerBuilder->has('lmc_cqrs.query_handler.http'));
        $this->assertFalse($containerBuilder->has(HttpQueryHandler::class));

        $this->assertFalse($containerBuilder->has('lmc_cqrs.send_command_handler.http'));
        $this->assertFalse($containerBuilder->has(HttpSendCommandHandler::class));

        $this->assertFalse($containerBuilder->has('lmc_cqrs.response_decoder.http'));
        $this->assertFalse($containerBuilder->has(HttpMessageResponseDecoder::class));

        $this->assertFalse($containerBuilder->has('lmc_cqrs.response_decoder.stream'));
        $this->assertFalse($containerBuilder->has(StreamResponseDecoder::class));

        $this->assertFalse($containerBuilder->has('lmc_cqrs.profiler_formatter.http'));
        $this->assertFalse($containerBuilder->has(HttpProfilerFormatter::class));
    }

    private function assertNoSolrExtensionSettings(ContainerBuilder $containerBuilder): void
    {
        $this->assertFalse($containerBuilder->has('lmc_cqrs.query_handler.solr'));
        $this->assertFalse($containerBuilder->has(SolrQueryHandler::class));

        $this->assertFalse($containerBuilder->has('lmc_cqrs.query_builder'));
        $this->assertFalse($containerBuilder->has(QueryBuilder::class));

        $this->assertFalse($containerBuilder->has(ApplicatorFactory::class));

        $expectedApplicators = [
            EntityApplicator::class,
            FacetsApplicator::class,
            FilterApplicator::class,
            FiltersApplicator::class,
            FulltextApplicator::class,
            FulltextBigramApplicator::class,
            FulltextBoostApplicator::class,
            GroupingApplicator::class,
            GroupingFacetApplicator::class,
            ParameterizedApplicator::class,
            SortApplicator::class,
            StatsApplicator::class,
        ];

        foreach ($expectedApplicators as $expectedApplicator) {
            $this->assertFalse($containerBuilder->has($expectedApplicator));
        }
    }

    /**
     * @test
     */
    public function shouldSetUpCacheForServices(): void
    {
        $configs = [
            [
                'cache' => [
                    'enabled' => true,
                    'cache_provider' => '@my.cache_data',
                ],
            ],
        ];

        $this->extension->load($configs, $this->containerBuilder);

        $this->assertDefaultSettings($this->containerBuilder);
        $this->assertCacheSettings($this->containerBuilder, 'my.cache_data', '@my.cache_data');
        $this->assertNoProfilerSettings($this->containerBuilder);
        $this->assertNoDebugSettings($this->containerBuilder);
        $this->assertNoHttpExtensionSettings($this->containerBuilder);
        $this->assertNoSolrExtensionSettings($this->containerBuilder);
    }

    private function assertCacheSettings(
        ContainerBuilder $containerBuilder,
        string $providerAlias,
        string $expectedProvider
    ): void {
        $containerBuilder
            ->register($providerAlias)
            ->setSynthetic(true)
            ->setClass('cache_class');

        $this->assertTrue($containerBuilder->getParameter('lmc_cqrs.cache.enabled'));
        $this->assertSame($expectedProvider, $containerBuilder->getParameter('lmc_cqrs.cache.provider'));

        $this->assertTrue($containerBuilder->has('lmc_cqrs.cache_provider'));
        $this->assertSame('cache_class', $containerBuilder->findDefinition('lmc_cqrs.cache_provider')->getClass());
    }

    /**
     * @test
     */
    public function shouldSetUpAllServices(): void
    {
        $configs = [
            [
                'profiler' => true,
                'debug' => true,

                'cache' => [
                    'enabled' => true,
                    'cache_provider' => 'my.cache.provider',
                ],

                'extension' => [
                    'http' => true,
                    'solr' => true,
                ],
            ],
        ];

        $this->extension->load($configs, $this->containerBuilder);

        $this->assertDefaultSettings($this->containerBuilder);
        $this->assertCacheSettings($this->containerBuilder, 'my.cache.provider', 'my.cache.provider');
        $this->assertProfilerSettings($this->containerBuilder);
        $this->assertDebugSettings($this->containerBuilder);
        $this->assertHttpExtensionSettings($this->containerBuilder);
        $this->assertSolrExtensionSettings($this->containerBuilder);
    }

    /**
     * @test
     */
    public function shouldSetUpServicesForProfiler(): void
    {
        $configs = [
            [
                'profiler' => true,
            ],
        ];

        $this->extension->load($configs, $this->containerBuilder);

        $this->assertDefaultSettings($this->containerBuilder);
        $this->assertNoCacheSettings($this->containerBuilder);
        $this->assertProfilerSettings($this->containerBuilder);
        $this->assertNoDebugSettings($this->containerBuilder);
        $this->assertNoHttpExtensionSettings($this->containerBuilder);
        $this->assertNoSolrExtensionSettings($this->containerBuilder);
    }

    private function assertProfilerSettings(ContainerBuilder $containerBuilder): void
    {
        $this->assertTrue($containerBuilder->has(CacheController::class));

        $this->assertTrue($containerBuilder->has(CqrsDataCollector::class));
        $cqrsDataCollectorDefinition = $containerBuilder->findDefinition(CqrsDataCollector::class);
        $this->assertTaggedIterator(
            'lmc_cqrs.profiler_formatter',
            'getDefaultPriority',
            $cqrsDataCollectorDefinition->getArgument('$formatters')
        );
        $this->assertTrue($cqrsDataCollectorDefinition->hasTag('data_collector'));
        $dataCollectorTag = $cqrsDataCollectorDefinition->getTag('data_collector')[0];
        $this->assertSame('@LmcCqrs/Profiler/index.html.twig', $dataCollectorTag['template']);
        $this->assertSame('cqrs', $dataCollectorTag['id']);

        $this->assertHasServiceWithAlias('lmc_cqrs.profiler_bag', ProfilerBag::class, $containerBuilder);

        $this->assertHasDefinitionWithPriority(
            JsonProfilerFormatter::class,
            'lmc_cqrs.profiler_formatter',
            -1,
            $containerBuilder
        );
        $this->assertHasDefinitionWithPriority(
            ErrorProfilerFormatter::class,
            'lmc_cqrs.profiler_formatter',
            -1,
            $containerBuilder
        );
    }

    /**
     * @test
     */
    public function shouldSetUpServicesForDebug(): void
    {
        $configs = [
            [
                'debug' => true,
            ],
        ];

        $this->extension->load($configs, $this->containerBuilder);

        $this->assertDefaultSettings($this->containerBuilder);
        $this->assertNoCacheSettings($this->containerBuilder);
        $this->assertNoProfilerSettings($this->containerBuilder);
        $this->assertDebugSettings($this->containerBuilder);
        $this->assertNoHttpExtensionSettings($this->containerBuilder);
        $this->assertNoSolrExtensionSettings($this->containerBuilder);
    }

    private function assertDebugSettings(ContainerBuilder $containerBuilder): void
    {
        $this->assertTrue($containerBuilder->has(DebugCqrsCommand::class));
        $definition = $containerBuilder->findDefinition(DebugCqrsCommand::class);
        $bindings = $definition->getBindings();

        $expectedBoundArgs = [
            '$cacheProvider' => '%lmc_cqrs.cache.provider%',
            '$isExtensionHttpEnabled' => '%lmc_cqrs.extension.http%',
            '$isExtensionSolrEnabled' => '%lmc_cqrs.extension.solr%',
        ];

        foreach ($expectedBoundArgs as $arg => $value) {
            $this->assertArrayHasKey($arg, $bindings);
            $this->assertSame($value, $bindings[$arg]->getValues()[0]);
        }
    }

    /**
     * @test
     */
    public function shouldSetUpServicesForHttpExtension(): void
    {
        $configs = [
            [
                'extension' => [
                    'http' => true,
                ],
            ],
        ];

        $this->extension->load($configs, $this->containerBuilder);

        $this->assertDefaultSettings($this->containerBuilder);
        $this->assertNoCacheSettings($this->containerBuilder);
        $this->assertNoProfilerSettings($this->containerBuilder);
        $this->assertNoDebugSettings($this->containerBuilder);
        $this->assertHttpExtensionSettings($this->containerBuilder);
        $this->assertNoSolrExtensionSettings($this->containerBuilder);
    }

    private function assertHttpExtensionSettings(ContainerBuilder $containerBuilder): void
    {
        $this->assertHasServiceWithAlias('lmc_cqrs.query_handler.http', HttpQueryHandler::class, $containerBuilder);
        $this->assertHasServiceWithAlias(
            'lmc_cqrs.send_command_handler.http',
            HttpSendCommandHandler::class,
            $containerBuilder
        );
        $this->assertHasServiceWithAliasTagAndPriority(
            'lmc_cqrs.response_decoder.http',
            HttpMessageResponseDecoder::class,
            'lmc_cqrs.response_decoder',
            90,
            $containerBuilder
        );
        $this->assertHasServiceWithAliasTagAndPriority(
            'lmc_cqrs.response_decoder.stream',
            StreamResponseDecoder::class,
            'lmc_cqrs.response_decoder',
            70,
            $containerBuilder
        );
        $this->assertHasServiceWithAlias(
            'lmc_cqrs.profiler_formatter.http',
            HttpProfilerFormatter::class,
            $containerBuilder
        );
    }

    /**
     * @test
     */
    public function shouldSetUpServicesForSolrExtension(): void
    {
        $configs = [
            [
                'extension' => [
                    'solr' => true,
                ],
            ],
        ];

        $this->extension->load($configs, $this->containerBuilder);

        $this->containerBuilder->register('solarium.client')->setSynthetic(true);

        $this->assertDefaultSettings($this->containerBuilder);
        $this->assertNoCacheSettings($this->containerBuilder);
        $this->assertNoProfilerSettings($this->containerBuilder);
        $this->assertNoDebugSettings($this->containerBuilder);
        $this->assertNoHttpExtensionSettings($this->containerBuilder);
        $this->assertSolrExtensionSettings($this->containerBuilder);
    }

    private function assertSolrExtensionSettings(ContainerBuilder $containerBuilder): void
    {
        $this->assertHasServiceWithAlias('lmc_cqrs.query_handler.solr', SolrQueryHandler::class, $containerBuilder);
        $this->assertReference(
            'solarium.client',
            $containerBuilder->findDefinition(SolrQueryHandler::class)->getArgument('$client')
        );

        $this->assertHasServiceWithAlias('lmc_cqrs.query_builder', QueryBuilder::class, $containerBuilder);

        $this->assertTrue($containerBuilder->has(ApplicatorFactory::class));
        $this->assertTaggedIterator(
            'lmc_cqrs.solr.query_builder_applicator',
            'getDefaultPriority',
            $containerBuilder->findDefinition(ApplicatorFactory::class)->getArgument('$availableApplicators')
        );

        $expectedApplicators = [
            EntityApplicator::class,
            FacetsApplicator::class,
            FilterApplicator::class,
            FiltersApplicator::class,
            FulltextApplicator::class,
            FulltextBigramApplicator::class,
            FulltextBoostApplicator::class,
            GroupingApplicator::class,
            GroupingFacetApplicator::class,
            ParameterizedApplicator::class,
            SortApplicator::class,
            StatsApplicator::class,
        ];

        foreach ($expectedApplicators as $expectedApplicator) {
            $this->assertTrue($containerBuilder->has($expectedApplicator));
        }

        $this->assertAutoconfiguredTags(
            ['lmc_cqrs.solr.query_builder_applicator' => ApplicatorInterface::class],
            $containerBuilder
        );
    }

    private function assertQueryFetcher(ContainerBuilder $containerBuilder): void
    {
        $this->assertTrue($containerBuilder->hasDefinition('lmc_cqrs.query_fetcher'));
        $this->assertTrue($containerBuilder->has(QueryFetcherInterface::class));
        $this->assertTrue($containerBuilder->has(QueryFetcher::class));

        $queryFetcherDefinition = $containerBuilder->findDefinition(QueryFetcherInterface::class);

        $this->assertSame('%lmc_cqrs.cache.enabled%', $queryFetcherDefinition->getArgument('$isCacheEnabled'));

        $this->assertReference('lmc_cqrs.cache_provider', $queryFetcherDefinition->getArgument('$cache'));
        $this->assertReference('lmc_cqrs.profiler_bag', $queryFetcherDefinition->getArgument('$profilerBag'));
    }

    private function assertCommandSender(ContainerBuilder $containerBuilder): void
    {
        $this->assertTrue($containerBuilder->hasDefinition('lmc_cqrs.command_sender'));
        $this->assertTrue($containerBuilder->has(CommandSenderInterface::class));
        $this->assertTrue($containerBuilder->has(CommandSender::class));

        $commandSenderDefinition = $containerBuilder->findDefinition(CommandSenderInterface::class);

        $this->assertReference('lmc_cqrs.profiler_bag', $commandSenderDefinition->getArgument('$profilerBag'));
    }
}
