<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 26/08/2016
 * Time: 14:59
 */

namespace CtSearchBundle\Command;


use CtSearchBundle\Classes\IndexManager;
use CtSearchBundle\Classes\Parameter;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends ContainerAwareCommand
{
  protected function configure(){
    $this
      ->setName('ctsearch:test')
      ->setDescription('Test command')
      ->addArgument('input', InputArgument::REQUIRED, 'Input')
    ;
  }
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $input = $input->getArgument('input');
    $output->writeln('INPUT = ' . $input);
    $output->writeln('OUTPUT = ' . Parameter::injectParameters($input));
  }

}