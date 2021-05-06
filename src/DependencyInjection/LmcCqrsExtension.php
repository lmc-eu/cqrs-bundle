<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\DependencyInjection;

use Lmc\Cqrs\Solr\QueryBuilder\Applicator\ApplicatorInterface;
use Lmc\Cqrs\Types\Decoder\ResponseDecoderInterface;
use Lmc\Cqrs\Types\Formatter\ProfilerFormatterInterface;
use Lmc\Cqrs\Types\QueryHandlerInterface;
use Lmc\Cqrs\Types\SendCommandHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Creates services based on bundle configuration
 */
class LmcCqrsExtension extends Extension
{
    public const TAG_QUERY_HANDLER = 'lmc_cqrs.query_handler';
    public const TAG_SEND_COMMAND_HANDLER = 'lmc_cqrs.send_command_handler';
    public const TAG_PROFILER_FORMATTER = 'lmc_cqrs.profiler_formatter';
    public const TAG_RESPONSE_DECODER = 'lmc_cqrs.response_decoder';
    public const TAG_SOLR_QUERY_BUILDER_APPLICATOR = 'lmc_cqrs.solr.query_builder_applicator';

    public const PARAMETER_CACHE_ENABLED = 'lmc_cqrs.cache.enabled';
    public const PARAMETER_CACHE_PROVIDER = 'lmc_cqrs.cache.provider';
    public const PARAMETER_EXTENSION_HTTP = 'lmc_cqrs.extension.http';
    public const PARAMETER_EXTENSION_SOLR = 'lmc_cqrs.extension.solr';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->autoconfigureTags($container);

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->setUpCache($config, $container);

        // Load pre-defined services from YAML
        $locator = new FileLocator(__DIR__ . '/../Resources/config');
        $loader = new Loader\YamlFileLoader($container, $locator);

        $loader->load('services-handler.yaml');

        $this->tryRegisterProfiler($config, $container, $loader);
        $this->tryRegisterDebug($config, $container, $loader);
        $this->tryRegisterHttpExtension($config, $container, $loader);
        $this->tryRegisterSolrExtension($config, $container, $loader);
    }

    private function autoconfigureTags(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(QueryHandlerInterface::class)
            ->addTag(self::TAG_QUERY_HANDLER);

        $container->registerForAutoconfiguration(SendCommandHandlerInterface::class)
            ->addTag(self::TAG_SEND_COMMAND_HANDLER);

        $container->registerForAutoconfiguration(ProfilerFormatterInterface::class)
            ->addTag(self::TAG_PROFILER_FORMATTER);

        $container->registerForAutoconfiguration(ResponseDecoderInterface::class)
            ->addTag(self::TAG_RESPONSE_DECODER);
    }

    private function setUpCache(array $config, ContainerBuilder $container): void
    {
        $cacheProvider = $config['cache']['cache_provider'];
        $isCacheEnabled =
            ($config['cache']['enabled'] === true) ||
            ($config['cache']['enabled'] === null && $cacheProvider !== null);

        $container->setParameter(self::PARAMETER_CACHE_ENABLED, $isCacheEnabled);
        $container->setParameter(self::PARAMETER_CACHE_PROVIDER, $cacheProvider);

        if ($cacheProvider) {
            $container->setAlias('lmc_cqrs.cache_provider', str_replace('@', '', $cacheProvider));
        }
    }

    private function tryRegisterProfiler(array $config, ContainerBuilder $container, YamlFileLoader $loader): void
    {
        if ($config['profiler']) {
            $loader->load('services-profiler.yaml');
        }
    }

    private function tryRegisterDebug(array $config, ContainerBuilder $container, YamlFileLoader $loader): void
    {
        if ($config['debug']) {
            $loader->load('services-debug.yaml');
        }
    }

    private function tryRegisterHttpExtension(array $config, ContainerBuilder $container, YamlFileLoader $loader): void
    {
        if ($config['extension']['http']) {
            $loader->load('services-http.yaml');
            $container->setParameter(self::PARAMETER_EXTENSION_HTTP, true);
        } else {
            $container->setParameter(self::PARAMETER_EXTENSION_HTTP, false);
        }
    }

    private function tryRegisterSolrExtension(array $config, ContainerBuilder $container, YamlFileLoader $loader): void
    {
        if ($config['extension']['solr']) {
            $container->registerForAutoconfiguration(ApplicatorInterface::class)
                ->addTag(self::TAG_SOLR_QUERY_BUILDER_APPLICATOR);

            $loader->load('services-solr.yaml');
            $container->setParameter(self::PARAMETER_EXTENSION_SOLR, true);
        } else {
            $container->setParameter(self::PARAMETER_EXTENSION_SOLR, false);
        }
    }
}
