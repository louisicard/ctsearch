<?php

namespace CtSearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface {
  
  public function getConfigTreeBuilder() {
    $treeBuilder = new TreeBuilder();
    $rootNode = $treeBuilder->root('ct_search');
    
    $rootNode
        ->children()
          ->scalarNode('es_url')->end()
    ;
    $rootNode
        ->children()
          ->scalarNode('debug')->end()
    ;
    
    return $treeBuilder;
  }
  
}