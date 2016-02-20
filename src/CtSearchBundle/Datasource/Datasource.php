<?php

namespace CtSearchBundle\Datasource;

use \CtSearchBundle\CtSearchBundle;
use \CtSearchBundle\Classes\IndexManager;

abstract class Datasource {

  /**
   *
   * @var \Symfony\Bundle\FrameworkBundle\Controller\Controller
   */
  private $controller;

  /**
   *
   * @var string
   */
  private $name;

  /**
   *
   * @var string
   */
  private $id;

  /**
   *
   * @var Symfony\Component\Console\Output\OutputInterface 
   */
  private $output;

  function __construct($name = '', \Symfony\Bundle\FrameworkBundle\Controller\Controller $controller = null, $id = null) {
    $this->controller = $controller;
    $this->name = $name;
    $this->id = $id;
  }

  function getController() {
    return $this->controller;
  }

  function getName() {
    return $this->name;
  }

  function setController(\Symfony\Bundle\FrameworkBundle\Controller\Controller $controller) {
    $this->controller = $controller;
  }

  function setName($name) {
    $this->name = $name;
  }

  function getId() {
    return $this->id;
  }

  function setId($id) {
    $this->id = $id;
  }

  /**
   * @return object
   */
  abstract function getSettings();

  /**
   * @param object $settings
   */
  abstract function initFromSettings($settings);

  /**
   * @return string
   */
  abstract function getDatasourceDisplayName();

  /**
   * @return string[]
   */
  abstract function getFields();

  /**
   * 
   * @param Datasource $source
   * @return \Symfony\Component\Form\FormBuilder
   */
  function getSettingsForm() {
    if ($this->getController() != null) {
      return $this->getController()->createFormBuilder($this)->add('name', 'text', array(
            'label' => $this->getController()->get('translator')->trans('Source name'),
            'required' => true
      ));
    } else {
      return null;
    }
  }

  /**
   * 
   * @param Datasource $source
   * @return \Symfony\Component\Form\FormBuilder
   */
  abstract function getExcutionForm();

  /**
   * 
   * @param Datasource $source
   */
  abstract function execute($execParams = null);

  protected function index($doc, $processors = null) {
    global $kernel;
    $esUrl = $kernel->getContainer()->getParameter('ct_search.es_url');
    $debug = $kernel->getContainer()->getParameter('ct_search.debug');
    $indexManager = new IndexManager($esUrl);
    try {
      if ($processors == null) {
        $processors = $indexManager->getRawProcessorsByDatasource($this->id);
      }
      foreach ($processors as $proc) {
        $data = array();
        foreach ($doc as $k => $v) {
          $data['datasource.' . $k] = $v;
        }
        $definition = json_decode($proc['definition'], true);
        foreach ($definition['filters'] as $filter) {
          $className = $filter['class'];
          $procFilter = new $className(array(), $indexManager);
          $procFilter->setOutput($this->getOutput());
          $filterData = array();
          foreach ($filter['settings'] as $k => $v) {
            $filterData['setting_' . $k] = $v;
          }
          foreach ($filter['arguments'] as $arg) {
            $filterData['arg_' . $arg['key']] = $arg['value'];
          }
          $procFilter->setData($filterData);
          $procFilter->setAutoImplode($filter['autoImplode']);
          $procFilter->setAutoImplodeSeparator($filter['autoImplodeSeparator']);
          $procFilter->setAutoStriptags($filter['autoStriptags']);
          $procFilter->setIsHTML($filter['isHTML']);
          $filterOutput = $procFilter->execute($data);
          //if($filter['id'] == 36840)
          //  $indexManager->log('debug', 'URL : ' . $data['datasource.url'], $filterOutput);
          if (empty($data)) {
            break;
          }
          foreach ($filterOutput as $k => $v) {
            if ($procFilter->getAutoImplode()) {
              $v = $this->implode($procFilter->getAutoImplodeSeparator(), $v);
            }
            if ($procFilter->getAutoStriptags()) {
              if ($procFilter->getIsHTML()) {
                if(!is_array($v)){
                  $v = $this->cleanNonUtf8Chars($this->extractTextFromHTML($v));
                }
                else{
                  foreach($v as $v_k => $v_v){
                    $v[$v_k] = $this->cleanNonUtf8Chars($this->extractTextFromHTML($v_v));
                  }
                }
              } else {
                if(!is_array($v)){
                  $v = $this->cleanNonUtf8Chars($this->extractTextFromXML($v));
                }
                else{
                  foreach($v as $v_k => $v_v){
                    $v[$v_k] = $this->cleanNonUtf8Chars($this->extractTextFromXML($v_v));
                  }
                }
              }
            }
            if ($v != null) {
              $data['filter_' . $filter['id'] . '.' . $k] = $v;
            }
          }
        }
        if (!empty($data)) {
          $to_index = array();
          foreach ($definition['mapping'] as $k => $input) {
            if (isset($data[$input])) {
              if (is_array($data[$input]) && count($data[$input]) == 1) {
                $to_index[$k] = $data[$input][0];
              } else {
                $to_index[$k] = $data[$input];
              }
            }
          }
          $target_r = explode('.', $definition['target']);
          $indexName = $target_r[0];
          $mappingName = $target_r[1];
          $indexManager->indexDocument($indexName, $mappingName, $to_index);
          $ac_settings = $indexManager->getACSettings($indexName);
          $ac_fields = array();
          if($ac_settings != null){
            foreach($ac_settings['fields'] as $field){
              if(explode('.', $field)[0] == $mappingName){
                $ac_fields[] = explode('.', $field)[1];
              }
            }
          }
          foreach($ac_fields as $field){
            if(isset($to_index[$field]) & !empty($to_index[$field])){
              $indexManager->feedAutocomplete($indexName, is_array($to_index[$field]) ? $to_index[$field][0] : $to_index[$field]);
              //$indexManager->log('debug', 'Feeding AC with content', is_array($to_index[$field]) ? $to_index[$field][0] : $to_index[$field]);
            }
          }
          if ($debug) {
            try {
              $indexManager->log('debug', 'Indexing document from datasource "' . $this->getName() . '"', $to_index);
            } catch (Exception $ex) {
              
            } catch (\Exception $ex2) {
              
            }
          }

        }
      }
      if(isset($processors))
        unset($processors);
      if(isset($data))
        unset($data);
      if(isset($to_index))
        unset($to_index);
    } catch (Exception $ex) {
      //var_dump($ex->getMessage());
      $indexManager->log('error', 'Exception occured while indexing document from datasource "' . $this->getName() . '"', array(
        'Exception type' => get_class($ex),
        'Message' => $ex->getMessage(),
        'File' => $ex->getFile(),
        'Line' => $ex->getLine(),
        'Data in process' => isset($data) ? $this->truncateArray($data) : array(),
      ));
    } catch (\Exception $ex2) {
      //var_dump($ex2);
      $indexManager->log('error', 'Exception occured while indexing document from datasource "' . $this->getName() . '"', array(
        'Exception type' => get_class($ex2),
        'Message' => $ex2->getMessage(),
        'File' => $ex2->getFile(),
        'Line' => $ex2->getLine(),
        'Data in process' => isset($data) ? $this->truncateArray($data) : array(),
      ));
    }
    
  }

  private function truncateArray($array) {
    foreach ($array as $k => $v) {
      if (is_string($v) && strlen($v) > 1000) {
        $array[$k] = substr($v, 0, 1000) . ' ... [TRUNCATED]';
      }
    }
    return $array;
  }

  protected function implode($separator, $input) {
    if(is_array($input))
      return implode($separator, $input);
    else
      return $input;
  }

  protected function extractTextFromHTML($html) {
    $html = str_replace('&nbsp;', ' ', $html);
    $html = str_replace('&rsquo;', ' ', $html);
    try {
      $tidy = tidy_parse_string($html, array(), 'utf8');
      $body = tidy_get_body($tidy);
      if($body != null)
        $html = $body->value;
    } catch (Exception $ex) {
      
    }
    $html = html_entity_decode($html, ENT_COMPAT | ENT_HTML401, 'utf-8');
    $html = trim(preg_replace('#<[^>]+>#', ' ', $html));
    $html_no_multiple_spaces = trim(preg_replace('!\s+!', ' ', $html));
    if(preg_match('!\s+!', $html) && !empty($html_no_multiple_spaces)){
      $html = $html_no_multiple_spaces;
    }
    $clean_html = html_entity_decode(trim(htmlentities($html, null, 'utf-8')));
    $r = empty($clean_html) ? $html : $clean_html;
    
    return $r;
  }

  protected function extractTextFromXML($xml) {
    return strip_tags($xml);
  }

  protected function batchIndex($docs) {
    $count = 0;
    $error = 0;
    global $kernel;
    $esUrl = $kernel->getContainer()->getParameter('ct_search.es_url');
    $indexManager = new IndexManager($esUrl);
    $processors = $indexManager->getRawProcessorsByDatasource($this->id);
    foreach ($docs as $doc) {
      try {
        $this->index($doc, $processors);
        $count++;
      } catch (Exception $ex) {
        $error++;
      } catch (\Exception $ex2) {
        $error++;
      }
    }
    if ($this->getController() != null) {
      CtSearchBundle::addSessionMessage($this->getController(), 'status', $count . ' document(s) indexed, ' . $error . ' error(s)');
    }
  }

  function getOutput() {
    return $this->output;
  }

  function setOutput($output) {
    $this->output = $output;
  }
  
  private function cleanNonUtf8Chars($text){
    if($text == null || empty($text)){
      return $text;
    }
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
    return preg_replace($regex, '$1', $text);
  }
}