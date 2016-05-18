<?php

namespace CtSearchBundle\Datasource;

use \CtSearchBundle\CtSearchBundle;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CMISHarvester extends Datasource {

  private $cmisEndpointUrl;
  private $username;
  private $password;
  private $tikaPath;
  private $folderPath;
  private $numberOfDocsPerCall;
  private $maxFileSizeToIndex;
  private $extensionsToIndex;

  public function getSettings() {
    return array(
      'cmisEndpointUrl' => $this->getCmisEndpointUrl() != null ? $this->getCmisEndpointUrl() : '',
      'username' => $this->getUsername() != null ? $this->getUsername() : '',
      'password' => $this->getPassword() != null ? $this->getPassword() : '',
      'tikaPath' => $this->getTikaPath() != null ? $this->getTikaPath() : '',
      'folderPath' => $this->getFolderPath() != null ? $this->getFolderPath() : '',
      'numberOfDocsPerCall' => $this->getNumberOfDocsPerCall() != null ? $this->getNumberOfDocsPerCall() : '',
      'maxFileSizeToIndex' => $this->getMaxFileSizeToIndex() != null ? $this->getMaxFileSizeToIndex() : '',
      'extensionsToIndex' => $this->getExtensionsToIndex() != null ? $this->getExtensionsToIndex() : '',
    );
  }

  public function initFromSettings($settings) {
    foreach ($settings as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function execute($execParams = null) {
    if ($execParams == null || !isset($execParams['daysBack']) || (int) $execParams['daysBack'] == 0) {
      if ($this->getOutput() != null) {
        $this->getOutput()->writeln('Missing "daysBack" parameter');
      }
    } else {
      $daysBack = (int) $execParams['daysBack'];
      $settings = $this->getSettings();
      $client = new \CMISService($settings['cmisEndpointUrl'], $settings['username'], $settings['password']);

      $folder = $client->getObjectByPath(urlencode($settings['folderPath']));
      $folderId = $folder->properties['cmis:objectId'];
      if (isset($settings['extensionsToIndex'])) {
        $fulltext_extensions = array_map('trim', explode(',', $settings['extensionsToIndex']));
      }

      try {
        $list = $this->getDocuments($client, $daysBack, $folderId, $settings['numberOfDocsPerCall']);
        $count = 0;
        while (count($list) > 0) {
          foreach ($list as $doc) {
            $count++;
            $to_index = array();
            $doc = $client->getObject($doc->properties['cmis:objectId']);
            if ($this->getOutput() != null) {
              $this->getOutput()->writeln($count . "/ " . $doc->properties['cmis:name'] . ' => ' . $doc->properties['cmis:contentStreamLength']);
            }
            $pathinfo = pathinfo($doc->properties['cmis:name']);
            $extension = isset($pathinfo['extension']) ? $pathinfo['extension'] : null;
            if ($extension != null && isset($settings['tikaPath']) && $doc->properties['cmis:contentStreamLength'] < $settings['maxFileSizeToIndex'] && in_array(strtolower($extension), $fulltext_extensions)) {
              $to_index['full_text'] = $this->getFulltext($client, $settings['tikaPath'], sys_get_temp_dir(), $doc);
            }
            if(isset($doc->links)){
              $to_index['links'] = $doc->links;
            }
            if(isset($doc->properties)){
              $to_index['properties'] = $doc->properties;
            }
            if(isset($doc->renditions)){
              $to_index['renditions'] = $doc->renditions;
            }
            if(isset($doc->uuid)){
              $to_index['uuid'] = $doc->uuid;
            }
            if(isset($doc->id)){
              $to_index['id'] = $doc->id;
            }
            if(isset($doc->allowableActions)){
              $to_index['allowableActions'] = $doc->allowableActions;
            }
            $parents_r = $client->getObjectParents($doc->properties['cmis:objectId']);
            if(isset($parents_r->objectList) && count($parents_r->objectList) > 0){
              $parent = $parents_r->objectList[0];
              if(isset($parent->properties['cmis:path'])){
                $to_index['parent_path'] = $parent->properties['cmis:path'];
              }
              if(isset($parent->properties['cmis:objectId'])){
                $to_index['parent_id'] = $parent->properties['cmis:objectId'];
              }
            }
            $this->index($to_index);
          }
          $list = $this->getDocuments($client, $daysBack, $folderId, $settings['numberOfDocsPerCall'], $count);
        }
      } catch (Exception $ex) {
        print $ex->getMessage();
      }

      if ($this->getController() != null) {
        CtSearchBundle::addSessionMessage($this->getController(), 'status', 'Found ' . $count . ' documents');
      }
    }
  }

  private function getDocuments($client, $nbDays, $folderId, $count, $offset = 0) {
    $date = new \DateTime();
    $date->sub(new \DateInterval('P' . $nbDays . 'D'));
    $query = "SELECT * FROM cmis:document WHERE IN_TREE('" . $folderId . "') AND cmis:lastModificationDate> TIMESTAMP '" . $date->format('Y-m-d\TH:i:s.000P') . "' ORDER BY cmis:lastModificationDate ASC";
    $result = $client->query($query, array(
      'maxItems' => $count,
      'skipCount' => $offset
    ));
    return $result->objectList;
  }

  private function getFulltext($client, $tika_path, $tmp_dir, $doc) {
    if(!file_exists($tika_path))
      return '';
    $extension = pathinfo($doc->properties['cmis:name'])['extension'];
    $filename = $tmp_dir . '/' . uniqid() . '.' . $extension;
    file_put_contents($filename, $client->getContentStream($doc->properties['cmis:objectId']));
    $cmd = 'java -jar "' . $tika_path . '" -h ' . $filename . ' -eutf-8';
    $out = array();
    exec($cmd, $out);
    $out = implode(' ', $out);
    $out = trim(preg_replace('!\s+!', ' ', strip_tags(str_replace('>', '> ', html_entity_decode($out, ENT_COMPAT | ENT_HTML401, 'UTF-8')))));
    $regex = <<<'END'
/
  (
    (?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3 
    ){1,100}                        # ...one or more times
  )
| .                                 # anything else
/x
END;
    $out = preg_replace($regex, '$1', $out);
    unlink($filename);
    return $out;
  }

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('cmisEndpointUrl', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('CMIS endpointURL'),
            'required' => true
          ))
          ->add('username', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('Username'),
            'required' => true
          ))
          ->add('password', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('Password'),
            'required' => true
          ))
          ->add('tikaPath', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('TIKA path')
          ))
          ->add('folderPath', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('Root path to harvest'),
            'required' => true
          ))
          ->add('numberOfDocsPerCall', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('Number of docs per call'),
            'required' => true
          ))
          ->add('maxFileSizeToIndex', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('Max file size to fulltext index')
          ))
          ->add('extensionsToIndex', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('Extensions to fulltext index (comma-separated)')
          ))
          ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Save')));
      return $formBuilder;
    } else {
      return null;
    }
  }

  public function getExcutionForm() {
    $formBuilder = $this->getController()->createFormBuilder()
        ->add('daysBack', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Number of days to index')
        ))
        ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getDatasourceDisplayName() {
    return 'CMIS Harvester';
  }

  public function getFields() {
    return array(
      'links',
      'properties',
      'renditions',
      'uuid',
      'id',
      'allowableActions',
      'full_text',
      'parent_path',
      'parent_id'
    );
  }

  function getCmisEndpointUrl() {
    return $this->cmisEndpointUrl;
  }

  function getUsername() {
    return $this->username;
  }

  function getPassword() {
    return $this->password;
  }

  function getTikaPath() {
    return $this->tikaPath;
  }

  function getFolderPath() {
    return $this->folderPath;
  }

  function getNumberOfDocsPerCall() {
    return $this->numberOfDocsPerCall;
  }

  function getMaxFileSizeToIndex() {
    return $this->maxFileSizeToIndex;
  }

  function getExtensionsToIndex() {
    return $this->extensionsToIndex;
  }

  function setCmisEndpointUrl($cmisEndpointUrl) {
    $this->cmisEndpointUrl = $cmisEndpointUrl;
  }

  function setUsername($username) {
    $this->username = $username;
  }

  function setPassword($password) {
    $this->password = $password;
  }

  function setTikaPath($tikaPath) {
    $this->tikaPath = $tikaPath;
  }

  function setFolderPath($folderPath) {
    $this->folderPath = $folderPath;
  }

  function setNumberOfDocsPerCall($numberOfDocsPerCall) {
    $this->numberOfDocsPerCall = $numberOfDocsPerCall;
  }

  function setMaxFileSizeToIndex($maxFileSizeToIndex) {
    $this->maxFileSizeToIndex = $maxFileSizeToIndex;
  }

  function setExtensionsToIndex($extensionsToIndex) {
    $this->extensionsToIndex = $extensionsToIndex;
  }

}
