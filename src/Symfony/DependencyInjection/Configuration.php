<?php

namespace MbpCoder\Payment\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('payment');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('default')
                    ->defaultValue('zarinpal')
                    ->info('Default gateway used when no name is given.')
                ->end()
                ->scalarNode('callback_url')
                    ->defaultNull()
                    ->info('Default callback URL the gateway returns the user to.')
                ->end()
                ->arrayNode('provider')
                    ->info('Per-gateway configuration (token, urls, ...).')
                    ->useAttributeAsKey('name')
                    ->variablePrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
