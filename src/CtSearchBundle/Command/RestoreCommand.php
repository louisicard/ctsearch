<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 26/08/2016
 * Time: 14:59
 */

namespace CtSearchBundle\Command;


use CtSearchBundle\Classes\Index;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RestoreCommand extends ContainerAwareCommand
{
  protected function configure(){
    $this
      ->setName('ctsearch:restore')
      ->setDescription('Restore an index')
      ->addArgument('repositoryName', InputArgument::REQUIRED, 'Repository name')
      ->addArgument('snapshotName', InputArgument::REQUIRED, 'Snapshot name')
      ->addArgument('source', InputArgument::REQUIRED, 'Source backup')
      ->addArgument('target', InputArgument::REQUIRED, 'Target')
    ;
  }

  /**
   * @var OutputInterface
   */
  protected $output;

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->output = $output;
    $source = $input->getArgument('source');
    $target = $input->getArgument('target');
    $params = array(
      'indices' => [$source],
      'ignore_unavailable' => false,
      'include_global_state' => false,
      'rename_pattern' => '(.+)',
      'rename_replacement' => $target
    );
    $this->output->writeln('Starting to restore...');
    IndexManager::getInstance()->restoreSnapshot($input->getArgument('repositoryName'), $input->getArgument('snapshotName'), $params);
    $this->output->writeln('Done!');
  }


}