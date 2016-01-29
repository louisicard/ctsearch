<?php

namespace CtSearchBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class CtSearchExtension extends Extension {
  
  public function load(array $configs, ContainerBuilder $container) {
    $configuration = new Configuration();
    $config = $this->processConfiguration($configuration, $configs);
    if(count($configs) > 0){
      foreach($configs[0] as $k => $v){
        $container->setParameter('ct_search.' . $k, $v);
      }
    }
    //loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
    //$loader->load('services.yml');
  }
  
}