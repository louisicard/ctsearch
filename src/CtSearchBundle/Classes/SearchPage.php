<?php

namespace CtSearchBundle\Classes;

class SearchPage {
  
  private $id;
  private $name;
  private $mapping;
  private $definition;
  
  function __construct($name, $mapping, $definition, $id = null) {
    $this->id = $id;
    $this->name = $name;
    $this->mapping = $mapping;
    $this->definition = $definition;
  }

  /**
   * @return null
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param null $id
   */
  public function setId($id)
  {
    $this->id = $id;
  }

  /**
   * @return mixed
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * @param mixed $name
   */
  public function setName($name)
  {
    $this->name = $name;
  }

  /**
   * @return mixed
   */
  public function getMapping()
  {
    return $this->mapping;
  }

  /**
   * @param mixed $mapping
   */
  public function setMapping($mapping)
  {
    $this->mapping = $mapping;
  }

  /**
   * @return mixed
   */
  public function getDefinition()
  {
    return $this->definition;
  }

  /**
   * @param mixed $definition
   */
  public function setDefinition($definition)
  {
    $this->definition = $definition;
  }

}
