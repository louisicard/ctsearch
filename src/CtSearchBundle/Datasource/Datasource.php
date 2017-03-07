<?php

namespace CtSearchBundle\Datasource;

use CtSearchBundle\Classes\Exportable;
use CtSearchBundle\Classes\Importable;
use CtSearchBundle\Controller\CtSearchController;
use \CtSearchBundle\CtSearchBundle;
use \CtSearchBundle\Classes\IndexManager;
use CtSearchBundle\Processor\ProcessorFilter;
use CtSearchBundle\Processor\SmartMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

abstract class Datasource implements Exportable, Importable {

  /**
   *
   * @var CtSearchController
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
   * @var boolean
   */
  private $hasBatchExecution;

  /**
   * @var string
   */
  private $createdBy;

  /**
   *
   * @var Symfony\Component\Console\Output\OutputInterface 
   */
  private $output;

  function __construct($name = '', CtSearchController $controller = null, $id = null) {
    $this->controller = $controller;
    $this->name = $name;
    $this->id = $id;
  }

  /**
   * @return CtSearchController
   */
  function getController() {
    return $this->controller;
  }

  function getName() {
    return $this->name;
  }

  function setController(CtSearchController $controller) {
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
   * @return string
   */
  public function getCreatedBy()
  {
    return $this->createdBy;
  }

  /**
   * @param string $createdBy
   */
  public function setCreatedBy($createdBy)
  {
    $this->createdBy = $createdBy;
  }


  /**
   * @return boolean
   */
  public function isHasBatchExecution()
  {
    return $this->hasBatchExecution;
  }

  /**
   * @param boolean $hasBatchExecution
   */
  public function setHasBatchExecution($hasBatchExecution)
  {
    $this->hasBatchExecution = $hasBatchExecution;
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
      return $this->getController()->createFormBuilder($this)
        ->add('name', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Source name'),
          'required' => true))
        ->add('hasBatchExecution', CheckboxType::class, array(
          'label' => $this->getController()->get('translator')->trans('Batch execution?'),
          'required' => false
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
  public function execute($execParams = null){
    if($this->isHasBatchExecution()) {
      $this->emptyBatchStack();
    }
  }

  protected function index($doc, $processors = null) {
    global $kernel;
    $debug = $kernel->getContainer()->getParameter('ct_search.debug');
    $startTime = round(microtime(true) * 1000);
    $debugTimeStat = [];
    try {
      if ($processors == null) {
        $processors = IndexManager::getInstance()->getRawProcessorsByDatasource($this->id);
      }
      $smartMappersToDump = [];
      foreach ($processors as $proc) {
        $data = array();
        foreach ($doc as $k => $v) {
          $data['datasource.' . $k] = $v;
        }
        $definition = json_decode($proc['definition'], true);
        foreach ($definition['filters'] as $filter) {
          $filterStartTime = round(microtime(true) * 1000);
          $className = $filter['class'];
          $procFilter = new $className(array(), IndexManager::getInstance());
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
          if(get_class($procFilter) == SmartMapper::class){
            /** @var ProcessorFilter $procFilter */
            $smartSettings = $procFilter->getSettings();
            if(isset($smartSettings['force_index']) && $smartSettings['force_index']){
              if(isset($filterOutput['smart_array'])) {
                $smartMappersToDump[] = $filterOutput['smart_array'];
              }
            }
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
            unset($v);
          }
          if($debug){
            $debugTimeStat['filter_' . $filter['id']] = round(microtime(true) * 1000) - $filterStartTime;
          }
          unset($filter);
          unset($procFilter);
          unset($filterOutput);
          unset($filterData);
        }
        if (!empty($data)) {
          $to_index = array();
          foreach ($definition['mapping'] as $k => $input) {
            if(strpos($input, '.smart_array') === FALSE) {
              if (isset($data[$input])) {
                if (is_array($data[$input]) && count($data[$input]) == 1) {
                  $to_index[$k] = $data[$input][0];
                } else {
                  $to_index[$k] = $data[$input];
                }
              }
            }
            else{
              if (isset($data[$input][$k])) {
                if (is_array($data[$input][$k]) && count($data[$input][$k]) == 1) {
                  $to_index[$k] = $data[$input][$k][0];
                } else {
                  $to_index[$k] = $data[$input][$k];
                }
              }
            }
          }
          //taking care of smart mappers which force indexing all their fields
          foreach($smartMappersToDump as $smartMapper){
            foreach($smartMapper as $k => $v){
              if(!is_array($v)) {
                $to_index[$k] = trim($this->cleanNonUtf8Chars($v));
              }
              else{
                foreach($v as $vv){
                  if(is_array($vv)){
                    $to_index[$k][] = trim($this->cleanNonUtf8Chars($vv));
                  }
                }
              }
            }
          }
          $target_r = explode('.', $definition['target']);
          $indexName = $target_r[0];
          $mappingName = $target_r[1];
          $indexStartTime = round(microtime(true) * 1000);
          $this->indexDocument($indexName, $mappingName, $to_index);
          if ($debug && !$this->isHasBatchExecution()) {
            try {
              $debugTimeStat['indexing'] = round(microtime(true) * 1000) - $indexStartTime;
              $debugTimeStat['global'] = round(microtime(true) * 1000) - $startTime;
              IndexManager::getInstance()->log('debug', 'Timing info', $debugTimeStat, $this);
              IndexManager::getInstance()->log('debug', 'Indexing document from datasource "' . $this->getName() . '"', $to_index, $this);
            } catch (Exception $ex) {
              
            } catch (\Exception $ex2) {
              
            }
          }

        }
        unset($proc);
      }
      if(isset($definition))
        unset($definition);
      if(isset($processors))
        unset($processors);
      if(isset($data))
        unset($data);
      if(isset($to_index))
        unset($to_index);
    } catch (Exception $ex) {
      //var_dump($ex->getMessage());
      IndexManager::getInstance()->log('error', 'Exception occured while indexing document from datasource "' . $this->getName() . '"', array(
        'Exception type' => get_class($ex),
        'Message' => $ex->getMessage(),
        'File' => $ex->getFile(),
        'Line' => $ex->getLine(),
        'Data in process' => isset($data) ? $this->truncateArray($data) : array(),
      ), $this);
    } catch (\Exception $ex2) {
      //var_dump($ex2);
      IndexManager::getInstance()->log('error', 'Exception occured while indexing document from datasource "' . $this->getName() . '"', array(
        'Exception type' => get_class($ex2),
        'Message' => $ex2->getMessage(),
        'File' => $ex2->getFile(),
        'Line' => $ex2->getLine(),
        'Data in process' => isset($data) ? $this->truncateArray($data) : array(),
      ), $this);
    }
    
    gc_enable();
    gc_collect_cycles();
    
  }

  private $batchStack = [];
  const BATCH_STACK_SIZE = 500;

  private function indexDocument($indexName, $mappingName, $to_index){
    if($this->isHasBatchExecution()){
      $this->batchStack[] = array(
        'indexName' => $indexName,
        'mappingName' => $mappingName,
        'body' => $to_index,
      );
      if(count($this->batchStack) >= static::BATCH_STACK_SIZE){
        $this->emptyBatchStack();
      }
    }
    else{
      IndexManager::getInstance()->indexDocument($indexName, $mappingName, $to_index);
    }
  }

  private function emptyBatchStack(){
    IndexManager::getInstance()->bulkIndex($this->batchStack);
    unset($this->batchStack);
    if ($this->getOutput() != null) {
      $this->getOutput()->writeln('Indexing documents in batch stack (stack size is ' . static::BATCH_STACK_SIZE . ')');
    }
    $this->batchStack = [];
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

  protected function multiIndex($docs) {
    $count = 0;
    $error = 0;
    $processors = IndexManager::getInstance()->getRawProcessorsByDatasource($this->id);
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

  public function export()
  {
    $export = array(
      'id' => $this->getId(),
      'type' => 'datasource',
      'class' => get_class($this),
      'id' => $this->getId(),
      'name' => $this->getName(),
      'has_batch_execution' => $this->isHasBatchExecution() ? 1 : 0,
      'settings' => $this->getSettings()
    );
    return json_encode($export, JSON_PRETTY_PRINT);
  }

  public static function import($data)
  {
    $datasource = new $data['class']($data['name'], null, $data['id']);
    $datasource->initFromSettings($data['settings']);
    $datasource->setHasBatchExecution($data['has_batch_execution']);
    $datasource->setId($data['id']);
    IndexManager::getInstance()->saveDatasource($datasource, $datasource->getId());
  }
}