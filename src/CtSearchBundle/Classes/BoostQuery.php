<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 16/03/2017
 * Time: 19:26
 */

namespace CtSearchBundle\Classes;


class BoostQuery
{

  /** @var  string */
  private $id;
  /** @var  string */
  private $target;
  /** @var  string */
  private $definition;

  /**
   * BoostQuery constructor.
   * @param string $id
   * @param string $target
   * @param string $definition
   */
  public function __construct($id, $target, $definition)
  {
    $this->id = $id;
    $this->target = $target;
    $this->definition = $definition;
  }

  /**
   * @return string
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param string $id
   */
  public function setId($id)
  {
    $this->id = $id;
  }

  /**
   * @return string
   */
  public function getTarget()
  {
    return $this->target;
  }

  /**
   * @param string $target
   */
  public function setTarget($target)
  {
    $this->target = $target;
  }

  /**
   * @return string
   */
  public function getDefinition()
  {
    return $this->definition;
  }

  /**
   * @param string $definition
   */
  public function setDefinition($definition)
  {
    $this->definition = $definition;
  }


}