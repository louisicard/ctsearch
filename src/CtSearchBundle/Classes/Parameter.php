<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 16/03/2017
 * Time: 19:26
 */

namespace CtSearchBundle\Classes;


class Parameter
{

  /** @var  string */
  private $name;
  /** @var  string */
  private $value;

  /**
   * Parameter constructor.
   * @param string $name
   * @param string $value
   */
  public function __construct($name, $value)
  {
    $this->name = $name;
    $this->value = $value;
  }

  public static function injectParameters($string) {
    preg_match_all('/(?<parameter>%[^%]*%)/i', $string, $matches);
    if(isset($matches['parameter'])) {
      foreach($matches['parameter'] as $param) {
        $name = trim($param, '%');
        $parameter = IndexManager::getInstance()->getParameter($name);
        if($parameter != null) {
          $string = str_replace('%' . $name . '%', $parameter->getValue(), $string);
        }
      }
    }
    return $string;
  }

  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * @param string $name
   */
  public function setName($name)
  {
    $this->name = $name;
  }

  /**
   * @return string
   */
  public function getValue()
  {
    return $this->value;
  }

  /**
   * @param string $value
   */
  public function setValue($value)
  {
    $this->value = $value;
  }


}