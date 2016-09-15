<?php

namespace CtSearchBundle\Datasource;

use CtSearchBundle\Classes\CurlUtils;
use \CtSearchBundle\CtSearchBundle;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CComptesOpenDataClient extends Datasource {

  private $url;
  private $linkField;

  public function getSettings() {
    return array(
      'linkField' => $this->getLinkField() != null ? $this->getLinkField() : '',
    );
  }

  public function initFromSettings($settings) {
    foreach ($settings as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function execute($execParams = null) {
    try {
      if(isset($execParams['url']) && !empty($execParams['url'])){
        $url = $execParams['url'];
        $data = $this->getContentFromUrl($url);
        $temp_file = tempnam(sys_get_temp_dir(), 'opendatatmp');
        $count = 0;
        if(file_put_contents($temp_file, $data)){
          $zip = zip_open($temp_file);
          $zip_data = [];
          while($entry = zip_read($zip)){
            $name_data = explode('/', zip_entry_name($entry));
            if(count($name_data) == 2 && !empty($name_data[1])) {
              $name = $name_data[1];
              $zip_data[$name] = zip_entry_read($entry, zip_entry_filesize($entry));
            }
          }
          if(isset($zip_data['export.csv'])){
            $csv = utf8_encode($zip_data['export.csv']);
            $csv_lines = explode(PHP_EOL, $csv);
            $doc = array();
            if(count($csv_lines) > 0) {
              $columns = array_map('trim', explode("\t", $csv_lines[0]));
            }
            for($i = 1; $i < count($csv_lines); $i++){
              foreach($columns as $index => $column){
                $csv_line_data = array_map('trim', explode("\t", $csv_lines[$i]));
                if(isset($csv_line_data[$index])) {
                  $doc['metadata'][$column] = $csv_line_data[$index];
                }
              }
              if(isset($doc['metadata'][$this->getLinkField()]) && isset($zip_data[$doc['metadata'][$this->getLinkField()]])){
                $doc['content'] = $zip_data[$doc['metadata'][$this->getLinkField()]];
              }
              $this->index($doc);
              $count++;
            }
          }
          $this->getOutput()->writeln($count . ' document(s) found');
          unset($zip_data);
          zip_close($zip);
          unlink($temp_file);
        }
      }
    } catch (Exception $ex) {
      print $ex->getMessage();
    }
    /*
    if ($this->getOutput() != null) {
      $this->getOutput()->writeln('Found ' . $count . ' documents');
    }
    if ($this->getController() != null) {
      CtSearchBundle::addSessionMessage($this->getController(), 'status', 'Found ' . $count . ' documents');
    }*/
  }

  private function getContentFromUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    CurlUtils::handleCurlProxy($ch);
    $r = curl_exec($ch);
    return $r;
  }

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder
          ->add('linkField', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('Field name that points to doc'),
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
        ->add('url', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Archive URL'),
          'required' => true
        ))
        ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getDatasourceDisplayName() {
    return 'OpenData Cour des comptes';
  }

  public function getFields() {
    return array(
      'metadata',
      'content'
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
  public function getLinkField()
  {
    return $this->linkField;
  }

  /**
   * @param mixed $linkField
   */
  public function setLinkField($linkField)
  {
    $this->linkField = $linkField;
  }

}
