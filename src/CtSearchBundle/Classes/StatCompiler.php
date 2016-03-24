<?php
/**
 * Created by PhpStorm.
 * User: Louis Sicard
 * Date: 22/03/2016
 * Time: 16:40
 */

namespace CtSearchBundle\Classes;


abstract class StatCompiler
{

  const STAT_PERIOD_MIN = 1;
  const STAT_PERIOD_HOUR = 2;
  const STAT_PERIOD_DAY = 3;
  const STAT_PERIOD_WEEK = 4;
  const STAT_PERIOD_MONTH = 5;
  const STAT_PERIOD_YEAR = 6;

  /**
   * @return string
   */
  abstract function getDisplayName();

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @param integer $period
   */
  abstract function compile($mapping, $from, $to, $period);

  /**
   * @return mixed
   */
  abstract function getHeaders();

  /**
   * @return string
   */
  abstract function getGoogleChartClass();

  /**
   * @return string
   */
  abstract function getJSData();

  /**
   * @param integer $period
   * @return string
   */
  public function getElasticPeriod($period){
    switch($period){
      case StatCompiler::STAT_PERIOD_MIN:
        return "minute";
      case StatCompiler::STAT_PERIOD_HOUR:
        return "hour";
      case StatCompiler::STAT_PERIOD_DAY:
        return "day";
      case StatCompiler::STAT_PERIOD_WEEK:
        return "week";
      case StatCompiler::STAT_PERIOD_MONTH:
        return "month";
      case StatCompiler::STAT_PERIOD_YEAR:
        return "year";
    }
    return "year";
  }

  /**
   * @var array
   */
  private $data = array();

  /**
   * @return array
   */
  public function getData()
  {
    return $this->data;
  }

  /**
   * @param array $data
   */
  protected function setData($data)
  {
    $this->data = $data;
  }



}