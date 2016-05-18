<?php
/**
 * Created by PhpStorm.
 * User: Louis Sicard
 * Date: 18/05/2016
 * Time: 15:31
 */

namespace CtSearchBundle\DependencyInjection;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CtSearchCompilerPass implements CompilerPassInterface
{
  public function process(ContainerBuilder $container)
  {
    $services = $container->findTaggedServiceIds("ctsearch.datasource");
    $container->setParameter("ctsearch.datasources", array_keys($services));
    $filterServices = $container->findTaggedServiceIds("ctsearch.filter");
    $container->setParameter("ctsearch.filters", array_keys($filterServices));
  }


}