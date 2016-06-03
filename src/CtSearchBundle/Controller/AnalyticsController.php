<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\Classes\StatCompiler;
use CtSearchBundle\CtSearchBundle;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsController extends Controller {

  /**
   * @Route("/analytics", name="analytics")
   */
  public function analyticsAction(Request $request) {

    $statChoices = [];

    foreach(get_declared_classes() as $class){
      if(is_subclass_of($class, StatCompiler::class)){
        $statChoices[(new $class())->getDisplayName()] = $class;
      }
    }

    $indexes = IndexManager::getInstance()->getElasticInfo($this);
    $targetChoices = array();
    foreach ($indexes as $indexName => $info) {
      $choices = array();
      if (isset($info['mappings'])) {
        foreach ($info['mappings'] as $mapping) {
          $choices[$indexName . '.' . $mapping['name']] = $indexName . '.' . $mapping['name'];
        }
      }
      $targetChoices[$indexName] = $choices;
    }

    $params = array(
      'title' => $this->get('translator')->trans('Analytics'),
      'main_menu_item' => 'analytics',
      'statChoices' => $statChoices,
      'mappingChoices' => $targetChoices,
    );
    return $this->render('ctsearch/analytics.html.twig', $params);
  }

  /**
   * @Route("/analytics/compile", name="analytics-compiler")
   */
  public function compileAction(Request $request) {

    $class = $request->get('stat');
    $compiler = new $class();
    /* @var StatCompiler $compiler */

    $from = \DateTime::createFromFormat('Y-m-d H:i:s', $request->get('date_from') . ' 00:00:00');
    $to = \DateTime::createFromFormat('Y-m-d H:i:s', $request->get('date_to') . ' 23:59:59');
    $period = $request->get('granularity');
    $mapping = $request->get('mapping');

    $compiler->compile($mapping, $from ? $from : null, $to ? $to : null, !empty($period) ? $period : StatCompiler::STAT_PERIOD_HOUR);

    $json = array(
      'headers' => $compiler->getHeaders(),
      'jsData' => $compiler->getJSData(),
      'data' => $compiler->getData(),
      'googleChartClass' => $compiler->getGoogleChartClass()
    );

    return new Response(json_encode($json, JSON_PRETTY_PRINT), 200, array('Content-type' => 'application/json;charset=utf-8'));
  }


}
