<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 07/03/2017
 * Time: 11:28
 */

namespace CtSearchBundle\Classes;


class Group
{

  /**
   * @var string
   */
  private $id;
  /**
   * @var string
   */
  private $name;
  /**
   * @var array
   */
  private $indexes;
  /**
   * @var array
   */
  private $datasources;
  /**
   * @var array
   */
  private $matchingLists;
  /**
   * @var array
   */
  private $dictionaries;

  /**
   * Group constructor.
   * @param string $id
   * @param string $name
   * @param array $indexes
   * @param array $datasources
   * @param array $matchingLists
   * @param array $dictionaries
   */
  public function __construct($id, $name, array $indexes, array $datasources, array $matchingLists, array $dictionaries)
  {
    $this->id = $id;
    $this->name = $name;
    $this->indexes = $indexes;
    $this->datasources = $datasources;
    $this->matchingLists = $matchingLists;
    $this->dictionaries = $dictionaries;
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
   * @return array
   */
  public function getIndexes()
  {
    return $this->indexes;
  }

  /**
   * @param array $indexes
   */
  public function setIndexes($indexes)
  {
    $this->indexes = $indexes;
  }

  /**
   * @return array
   */
  public function getDatasources()
  {
    return $this->datasources;
  }

  /**
   * @param array $datasources
   */
  public function setDatasources($datasources)
  {
    $this->datasources = $datasources;
  }

  /**
   * @return array
   */
  public function getMatchingLists()
  {
    return $this->matchingLists;
  }

  /**
   * @param array $matchingLists
   */
  public function setMatchingLists($matchingLists)
  {
    $this->matchingLists = $matchingLists;
  }

  /**
   * @return array
   */
  public function getDictionaries()
  {
    return $this->dictionaries;
  }

  /**
   * @param array $dictionaries
   */
  public function setDictionaries($dictionaries)
  {
    $this->dictionaries = $dictionaries;
  }

}