<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\DependencyInjection;

use Lmc\Cqrs\Handler\ProfilerBag;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('lmc_cqrs');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('profiler')
                    ->beforeNormalization()
                        ->ifTrue(fn ($v) => is_bool($v))
                        ->then(fn (bool $v) => ['enabled' => $v])
                    ->end()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('verbosity')
                            ->defaultValue(ProfilerBag::VERBOSITY_NORMAL)
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('debug')
                    ->defaultFalse()
                ->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('cache_provider')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('extension')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('http')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('solr')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
