<?php

namespace CtSearchBundle\Command;

use CtSearchBundle\Classes\IndexManager;
use CtSearchBundle\Classes\Parameter;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatasourceCommand extends ContainerAwareCommand {

  protected function configure() {
    $this
        ->setName('ctsearch:exec')
        ->setDescription('Execute a datasouce')
        ->addArgument('id', InputArgument::REQUIRED, 'Datasource id')
        ->addArgument('args', InputArgument::OPTIONAL, 'Datasource args in querystring format')
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $id = $input->getArgument('id');
    $args = $input->getArgument('args');
    $execParams = array();
    if ($args != null) {
      $args_r = explode('&', $args);
      foreach ($args_r as $args_rr) {
        $args_rr_r = explode('=', $args_rr);
        if (count($args_rr_r) == 2) {
          $execParams[$args_rr_r[0]] = Parameter::injectParameters($args_rr_r[1]);
        }
      }
    }
    $datasource = IndexManager::getInstance()->getDatasource($id, null);
    $datasource->setOutput($output);
    $output->writeln('Executing Datasource "' . $datasource->getName() . '"');
    $datasource->execute($execParams);
  }

}
