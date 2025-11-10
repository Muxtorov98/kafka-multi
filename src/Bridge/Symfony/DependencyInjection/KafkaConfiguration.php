<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class KafkaConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('kafka');
        $root = $tree->getRootNode();

        $root
            ->children()
            ->scalarNode('brokers')->isRequired()->end()

            ->arrayNode('consumer')
            ->children()
            ->arrayNode('kafka')->normalizeKeys(false)->scalarPrototype()->end()->end()
            ->integerNode('batch_size')->defaultValue(1)->end()
            ->end()
            ->end()

            ->arrayNode('producer')
            ->children()
            ->arrayNode('kafka')->normalizeKeys(false)->scalarPrototype()->end()->end()
            ->end()
            ->end()

            ->arrayNode('retry')
            ->children()
            ->integerNode('max_attempts')->defaultValue(3)->end()
            ->integerNode('backoff_ms')->defaultValue(500)->end()
            ->scalarNode('dlq_suffix')->defaultValue('-dlq')->end()
            ->end()
            ->end()

            ->arrayNode('discovery')
            ->children()
            ->arrayNode('namespaces')->scalarPrototype()->end()->defaultValue([])->end()
            ->arrayNode('paths')->scalarPrototype()->end()->defaultValue([])->end()
            ->end()
            ->end()
            ->end();

        return $tree;
    }
}