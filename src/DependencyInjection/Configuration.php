<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('lmc_cqrs');
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('profiler')
                    ->defaultFalse()
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
