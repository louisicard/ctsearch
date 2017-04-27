<?php

namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PDOQueryFilter extends ProcessorFilter
{

  public function getDisplayName()
  {
    return "PDO query";
  }

  public function getSettingsForm($controller)
  {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('setting_driver', TextType::class, array(
        'label' => $controller->get('translator')->trans('PD driver (E.g.: mysql, postgresl)'),
        'required' => true
      ))
      ->add('setting_host', TextType::class, array(
        'label' => $controller->get('translator')->trans('Host'),
        'required' => true
      ))
      ->add('setting_port', TextType::class, array(
        'label' => $controller->get('translator')->trans('Port'),
        'required' => true
      ))
      ->add('setting_dbName', TextType::class, array(
        'label' => $controller->get('translator')->trans('Database name'),
        'required' => true
      ))
      ->add('setting_username', TextType::class, array(
        'label' => $controller->get('translator')->trans('Username'),
        'required' => true
      ))
      ->add('setting_password', TextType::class, array(
        'label' => $controller->get('translator')->trans('Password'),
        'required' => true
      ))
      ->add('setting_retry_on_pdo_exception', CheckboxType::class, array(
        'required' => false,
        'label' => $controller->get('translator')->trans('Retry on PDO Exception'),
      ))
      ->add('setting_sql', TextareaType::class, array(
        'required' => true,
        'label' => $controller->get('translator')->trans('SQL query (use @varX for variable #X)'),
      ))
      ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }


  public function getFields()
  {
    return array('rows');
  }

  public function getArguments()
  {
    return array(
      'var1' => 'Variable #1',
      'var2' => 'Variable #2',
      'var3' => 'Variable #3',
      'var4' => 'Variable #4',
      'var5' => 'Variable #5'
    );
  }

  public function execute(&$document)
  {
    $settings = $this->getSettings();
    $tries = 0;
    $retry = isset($settings['retry_on_pdo_exception']) && $settings['retry_on_pdo_exception'];
    while($tries == 0 || $retry) {
      try {
        $dsn = $settings['driver'] . ':host=' . $settings['host'] . ';port=' . $settings['port'] . ';dbname=' . $settings['dbName'] . ';charset=UTF8;';
        $pdo = new \PDO($dsn, $settings['username'], $settings['password']);
        $sql = $settings['sql'];
        foreach ($this->getArguments() as $k => $v) {
          $sql = str_replace('@' . $k, $this->getArgumentValue($k, $document), $sql);
        }
        $rows = array();
        $rs = $pdo->query($sql);
        while ($row = $rs->fetch(\PDO::FETCH_ASSOC)) {
          $rows[] = $row;
        }
        $retry = false;
      }
      catch(\PDOException $ex){
        print get_class($this) . ' >> PDO Exception has been caught (' . $ex->getMessage() . ')' . PHP_EOL;
        if($tries > 20){
          $retry=  false;
          print get_class($this) . ' >> This is over, I choose to die.' . PHP_EOL;
          throw $ex;
        }
        else{
          print get_class($this) . ' >> Retrying in 1 second...' . PHP_EOL;
          sleep(1); //Sleep for 1 second
        }
      }
      finally{
        $tries++;
      }
    }
    return array('rows' => $rows);

  }

}
