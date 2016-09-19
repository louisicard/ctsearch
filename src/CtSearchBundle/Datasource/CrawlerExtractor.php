<?php

namespace CtSearchBundle\Datasource;

use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\CtSearchBundle;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CrawlerExtractor extends Datasource {

  private $url;
  private $linesToSkip;

  public function getSettings() {
    return array();
  }

  public function initFromSettings($settings) {
    foreach ($settings as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function execute($execParams = null) {
    try {
      if(isset($execParams['minTimestamp'])){
        $minTimestamp = (int)$execParams['minTimestamp'];
        $body = array(
          'query' => array(
            'range' => array(
              'crawl_time' => array(
                'gte' => date('Y-m-d\TH:i:s', $minTimestamp)
              )
            )
          ),
          'aggs' => array(
            'software' => array(
              'terms' => array(
                'field' => 'software',
                'size' => 20
              )
            )
          )
        );
        $res = IndexManager::getInstance()->search('crawler', json_encode($body), 0, 0);
        if(isset($res['aggregations']['software']['buckets'])){
          foreach($res['aggregations']['software']['buckets'] as $bucket){
            $software = $bucket['key'];
            $body2 = array(
              'query' => array(
                'bool' => array(
                  'must' => array(
                    array(
                      'range' => array(
                        'crawl_time' => array(
                          'gte' => $minTimestamp
                        )
                      )
                    ),
                    array(
                      'term' => array(
                        'software' => $software
                      )
                    )
                  )
                )
              ),
              'aggs' => array(
                'domain' => array(
                  'terms' => array(
                    'field' => 'domain',
                    'size' => 9999999
                  )
                )
              )
            );
            $res2 = IndexManager::getInstance()->search('crawler', json_encode($body2), 0, 0);
            if(isset($res2['aggregations']['domain']['buckets'])){
              foreach($res2['aggregations']['domain']['buckets'] as $bucket){
                $domain = $bucket['key'];
                $body3 = array(
                  'query' => array(
                    'bool' => array(
                      'must' => array(
                        array(
                          'term' => array(
                            'domain' => $domain
                          )
                        ),
                        array(
                          'term' => array(
                            'software' => $software
                          )
                        )
                      )
                    )
                  ),
                  'aggs' => array(
                    'parent_domain' => array(
                      'terms' => array(
                        'field' => 'parent_domain',
                        'size' => 9999999
                      )
                    )
                  )
                );
                $res3 = IndexManager::getInstance()->search('crawler', json_encode($body3), 0, 1);
                if(isset($res3['hits']['hits'][0]) && isset($res3['aggregations']['parent_domain']['buckets'])){
                  $parent_domain = NULL;
                  foreach($res3['aggregations']['parent_domain']['buckets'] as $bucket){
                    if($bucket['key'] != $domain){
                      $parent_domain = $bucket['key'];
                    }
                  }
                  $doc = array(
                    'domain' => $domain,
                    'software' => $software,
                    'server' => isset($res3['hits']['hits'][0]['_source']['server']) ? $res3['hits']['hits'][0]['_source']['server'] : null,
                    'powered_by' => isset($res3['hits']['hits'][0]['_source']['powered_by']) ? $res3['hits']['hits'][0]['_source']['powered_by'] : null,
                    'domain_origin' => $parent_domain,
                    'crawl_time' => isset($res3['hits']['hits'][0]['_source']['crawl_time']) ? $res3['hits']['hits'][0]['_source']['crawl_time'] : null
                  );
                  $this->index($doc);
                }
              }
            }
          }
        }
      }
    } catch (Exception $ex) {
      print $ex->getMessage();
    }
  }

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Save')));
      return $formBuilder;
    } else {
      return null;
    }
  }

  public function getExcutionForm() {
    $formBuilder = $this->getController()->createFormBuilder()
      ->add('minTimestamp', TextType::class, array(
        'label' => $this->getController()->get('translator')->trans('Min crawl timestamp'),
        'required' => true
      ))
      ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getDatasourceDisplayName() {
    return 'CT crawler extractor';
  }

  public function getFields() {
    return array(
      'domain',
      'software',
      'server',
      'powered_by',
      'domain_origin',
      'crawl_time'
    );
  }

}
