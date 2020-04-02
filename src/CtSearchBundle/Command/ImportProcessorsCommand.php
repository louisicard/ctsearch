<?php

namespace CtSearchBundle\Command;

use CtSearchBundle\Classes\IndexManager;
use CtSearchBundle\Classes\Processor;
use CtSearchBundle\Datasource\Datasource;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportProcessorsCommand extends ContainerAwareCommand
{

    /**
     * @var OutputInterface
     */
    private $output;
    /**
     * @var InputInterface
     */
    private $input;

    protected function configure()
    {
        $this
            ->setName('ctsearch:import-processors')
            ->setDescription('Import processors command')
            ->addArgument('path', InputArgument::OPTIONAL, 'file: import')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $path = $input->getArgument('path');
        $this->import($path);
    }

    private function import($path){

        if ($handle = opendir($path)) {
            while (false !== ($file = readdir($handle))) {
                if ('.' === $file) continue;
                if ('..' === $file) continue;
                $this->importJson($path."/".$file);
            }
            closedir($handle);
        }

    }

    private function importJson($json){
        $json = json_decode(file_get_contents($json), true);
        if($json != null && isset($json['type'])){
            switch($json['type']){
                case 'processor':
                    Processor::import($json);
                    break;
                default:
                    $this->output->writeln('Object type "' . $json['type'] . '" is not supported');
            }
        }
        else{
            $this->output->writeln('Cannot process import');
        }
    }


}
