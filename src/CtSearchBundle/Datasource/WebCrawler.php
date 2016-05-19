<?php

namespace CtSearchBundle\Datasource;

use CtSearchBundle\Classes\CurlUtils;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints as Assert;
use \CtSearchBundle\CtSearchBundle;

class WebCrawler extends Datasource {

  /**
   *
   * @Assert\Url()
   */
  private $serviceUrl;
  private $domain;

  /**
   * @Assert\Type("numeric")
   */
  private $numberOfCrawlers;

  /**
   * @Assert\Type("numeric")
   */
  private $crawlMax;

  /**
   * @Assert\Type("numeric")
   */
  private $crawlMaxDepth;

  /**
   * @Assert\Type("numeric")
   */
  private $politenessDelay;
  private $crawlerResumable;

  /**
   * @Assert\Type("numeric")
   */
  private $crawlMaxTime;
  private $userAgent;

  public function getSettings() {
    return array(
      'serviceUrl' => $this->getServiceUrl() != null ? $this->getServiceUrl() : '',
      'domain' => $this->getDomain() != null ? $this->getDomain() : '',
      'numberOfCrawlers' => $this->getNumberOfCrawlers() != null ? (int) $this->getNumberOfCrawlers() : 5,
      'crawlMax' => $this->getCrawlMax() != null ? (int) $this->getCrawlMax() : -1,
      'crawlMaxDepth' => $this->getCrawlMaxDepth() != null ? (int) $this->getCrawlMaxDepth() : -1,
      'politenessDelay' => $this->getPolitenessDelay() != null ? (int) $this->getPolitenessDelay() : 500,
      'crawlerResumable' => $this->getCrawlerResumable() != null ? $this->getCrawlerResumable() : false,
      'crawlMaxTime' => $this->getCrawlMaxTime() != null ? (int) $this->getCrawlMaxTime() : -1,
      'userAgent' => $this->getUserAgent() != null ? $this->getUserAgent() : 'Mozilla/5.0; ctSearch crawler',
    );
  }

  public function initFromSettings($settings) {
    foreach ($settings as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function execute($execParams = null) {
    //var_dump($execParams);
    //var_dump($this->getSettings());
    if ($execParams != null && isset($execParams['operation'])) {
      $operation = $execParams['operation'];
    } else {
      $operation = 'start';
    }
    $settings = $this->getSettings();
    $url = $settings['serviceUrl'];
    unset($settings['serviceUrl']);
    global $kernel;
    /** @var RequestStack $stack */
    $stack = $kernel->getContainer()->get('request_stack');
    $callbackUrl = $stack->getCurrentRequest()->getSchemeAndHttpHost() . $kernel->getContainer()->get('router')->getContext()->getBaseUrl() . '/webcrawler-response?datasourceId=' . $this->getId();
    $settings['callbackUrl'] = $callbackUrl;
    $settings['op'] = $operation;
    $serverResponse = $this->getRestData($url, $settings);
    //var_dump($serverResponse);
    if ($serverResponse != null && isset($serverResponse['status'])) {
      if ($serverResponse['status'] == 'OK') {
        if ($this->getController() != null)
          CtSearchBundle::addSessionMessage($this->getController(), 'status', 'Server response is OK');
      } elseif ($serverResponse['status'] == 'Error') {
        if ($this->getController() != null)
          CtSearchBundle::addSessionMessage($this->getController(), 'error', 'Server responded with error: "' . $serverResponse['message'] . '"');
      }
    } else {
      if ($this->getController() != null)
        CtSearchBundle::addSessionMessage($this->getController(), 'error', 'No valid response from server');
    }
  }

  private function getRestData($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'data=' . urlencode(json_encode($data)));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    CurlUtils::handleCurlProxy($ch);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
  }
  
  public function handleDataFromCallback($document){
    $this->index($document);
  }

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('serviceUrl', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Service url'),
          'required' => true
        ))
        ->add('domain', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Domain to crawl'),
          'required' => true
        ))
        ->add('numberOfCrawlers', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Number of crawlers'),
          'required' => false
        ))
        ->add('crawlMax', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Max number of pages to crawl'),
          'required' => false
        ))
        ->add('crawlMaxDepth', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Max depth of crawling'),
          'required' => false
        ))
        ->add('politenessDelay', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Politeness delay (ms)'),
          'required' => false
        ))
        ->add('crawlerResumable', CheckboxType::class, array(
          'label' => $this->getController()->get('translator')->trans('Crawler is resumable'),
          'required' => false
        ))
        ->add('crawlMaxTime', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Max crawling time (ms)'),
          'required' => false
        ))
        ->add('userAgent', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Crawler User-Agent'),
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
      ->add('operation', ChoiceType::class, array(
        'label' => 'Operation to perform',
        'choices' => array(
          $this->getController()->get('translator')->trans('Select an operation') => '',
          $this->getController()->get('translator')->trans('Start') => 'start',
          $this->getController()->get('translator')->trans('Stop') => 'stop',
        ),
        'required' => true
      ))
      ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getFields() {
    return array(
      'title',
      'html',
      'url',
    );
  }

  public function getDatasourceDisplayName() {
    return 'Web crawler';
  }

  function getServiceUrl() {
    return $this->serviceUrl;
  }

  function getDomain() {
    return $this->domain;
  }

  function getNumberOfCrawlers() {
    return $this->numberOfCrawlers;
  }

  function getCrawlMax() {
    return $this->crawlMax;
  }

  function getCrawlMaxDepth() {
    return $this->crawlMaxDepth;
  }

  function getPolitenessDelay() {
    return $this->politenessDelay;
  }

  function getCrawlerResumable() {
    return $this->crawlerResumable;
  }

  function getCrawlMaxTime() {
    return $this->crawlMaxTime;
  }

  function setServiceUrl($serviceUrl) {
    $this->serviceUrl = $serviceUrl;
  }

  function setDomain($domain) {
    $this->domain = $domain;
  }

  function setNumberOfCrawlers($numberOfCrawlers) {
    $this->numberOfCrawlers = $numberOfCrawlers;
  }

  function setCrawlMax($crawlMax) {
    $this->crawlMax = $crawlMax;
  }

  function setCrawlMaxDepth($crawlMaxDepth) {
    $this->crawlMaxDepth = $crawlMaxDepth;
  }

  function setPolitenessDelay($politenessDelay) {
    $this->politenessDelay = $politenessDelay;
  }

  function setCrawlerResumable($crawlerResumable) {
    $this->crawlerResumable = $crawlerResumable;
  }

  function setCrawlMaxTime($crawlMaxTime) {
    $this->crawlMaxTime = $crawlMaxTime;
  }
  function getUserAgent() {
    return $this->userAgent;
  }

  function setUserAgent($userAgent) {
    $this->userAgent = $userAgent;
  }


}
