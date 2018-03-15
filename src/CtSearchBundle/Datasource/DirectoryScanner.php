<?php

namespace CtSearchBundle\Datasource;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class DirectoryScanner extends Datasource
{

  protected $path;

  public function getSettings()
  {
    return array(
      'path' => $this->getPath() != null ? $this->getPath() : '',
    );
  }

  public function execute($execParams = null)
  {
    $path = isset($execParams['path']) ? $execParams['path'] : $this->getPath();
    if(file_exists($path) && is_dir($path)) {
      $path = realpath($path);
      $this->scanDirectory($path, function($file) {
        $this->index(array(
          'absolute_path' => $file,
          'info' => pathinfo($file)
        ));
      });
    }
    else {
      $this->getOutput()->writeln($path . ' is not a valid directory');
    }
    parent::execute($execParams);
  }

  private function scanDirectory($path, $callable = null) {
    $content = scandir($path);
    foreach($content as $c) {
      if($c != '.' && $c != '..') {
        if (is_dir($path . DIRECTORY_SEPARATOR . $c)) {
          $this->scanDirectory($path . DIRECTORY_SEPARATOR . $c, $callable);
        } else {
          if($callable != null) {
            $callable($path . DIRECTORY_SEPARATOR . $c);
          }
        }
      }
    }
  }

  public function getSettingsForm()
  {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('path', TextType::class, array(
        'label' => $this->getController()->get('translator')->trans('Directory path'),
        'required' => true
      ))
        ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Save')));
      return $formBuilder;
    } else {
      return null;
    }
  }

  public function getExcutionForm()
  {
    $formBuilder = $this->getController()->createFormBuilder()
      ->add('path', TextType::class, array(
        'label' => 'Directory path',
        'required' => true
      ))
      ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getFields()
  {
    return array('absolute_path', 'info');
  }

  public function getDatasourceDisplayName()
  {
    return 'Directory scanner';
  }

  /**
   * @return mixed
   */
  public function getPath()
  {
    return $this->path;
  }

  /**
   * @param mixed $path
   */
  public function setPath($path)
  {
    $this->path = $path;
  }


}
