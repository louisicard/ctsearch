<?php

namespace CtSearchBundle\Datasource;

use \CtSearchBundle\CtSearchBundle;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class TextFileParser extends Datasource {

  protected $url;
  protected $linesToSkip;

  public function getSettings() {
    return array(
      'url' => $this->getUrl() != null ? $this->getUrl() : '',
      'linesToSkip' => $this->getLinesToSkip() != null ? $this->getLinesToSkip() : '',
    );
  }

  public function execute($execParams = null) {
    try {
      if(isset($execParams['file']) && !empty($execParams['file'])){
        $file = $execParams['file'];
        /* @var $file \Symfony\Component\HttpFoundation\File\File */
        //$path = $file->getRealPath();
        $path = $file;
      }
      elseif(isset($this->getSettings()['url']) && !empty($this->getSettings()['url'])){
        $path = $this->getSettings()['url'];
      }
      $count = 0;
      $linesToSkip = isset($this->getSettings()['linesToSkip']) && !empty($this->getSettings()['linesToSkip']) ? $this->getSettings()['linesToSkip'] : 0;
      $linesToSkip = isset($execParams['linesToSkip']) && !empty($execParams['linesToSkip']) ? $execParams['linesToSkip'] : $linesToSkip;
      if(isset($path)){
        $fp = fopen($path, "r");
        if ($fp) {
          while (($line = fgets($fp)) !== false) {
            if($count >= $linesToSkip){
              $line = trim($line);
              if ($this->getOutput() != null) {
                $this->getOutput()->writeln('Processing line ' . ($count + 1));
              }
              $this->index(array('line' => $line));
            }
            $count++;
          }
          fclose($fp);
        } else {
          throw Exception('Error opening file "' . $path . '"');
        }
      }
    } catch (Exception $ex) {
      print $ex->getMessage();
    }

    if ($this->getOutput() != null) {
      $this->getOutput()->writeln('Processed ' . $count . ' documents');
    }
    if ($this->getController() != null) {
      CtSearchBundle::addSessionMessage($this->getController(), 'status', 'Found ' . $count . ' documents');
    }
    parent::execute($execParams);
  }

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('url', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('Text File url'),
            'required' => false
          ))
          ->add('linesToSkip', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('Number of lines to skip'),
            'required' => true
          ))
          ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Save')));
      return $formBuilder;
    } else {
      return null;
    }
  }

  public function getExcutionForm() {
    $formBuilder = $this->getController()->createFormBuilder()
      ->add('file', FileType::class, array(
        'label' => $this->getController()->get('translator')->trans('File'),
        'required' => false
      ))
      ->add('linesToSkip', TextType::class, array(
        'label' => $this->getController()->get('translator')->trans('Number of lines to skip'),
        'required' => false
      ))
        ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getDatasourceDisplayName() {
    return 'Text file Parser';
  }

  public function getFields() {
    return array(
      'line',
    );
  }
  function getUrl() {
    return $this->url;
  }

  function setUrl($url) {
    $this->url = $url;
  }

  /**
   * @return mixed
   */
  public function getLinesToSkip()
  {
    return $this->linesToSkip;
  }

  /**
   * @param mixed $linesToSkip
   */
  public function setLinesToSkip($linesToSkip)
  {
    $this->linesToSkip = $linesToSkip;
  }



}
