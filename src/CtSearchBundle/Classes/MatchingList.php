<?php

namespace CtSearchBundle\Classes;

class MatchingList {

  private $name;
  private $list;
  private $id;
  /**
   * @var string
   */
  private $createdBy;
  function __construct($name, $list = '{}', $id = null) {
    $this->name = $name;
    $this->list = $list;
    $this->id = $id;
  }
  function getName() {
    return $this->name;
  }

  function getList() {
    return $this->list;
  }

  function getId() {
    return $this->id;
  }

  function setName($name) {
    $this->name = $name;
  }

  function setList($list) {
    $this->list = $list;
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



}
