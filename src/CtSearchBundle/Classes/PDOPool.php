<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 27/04/2017
 * Time: 23:11
 */

namespace CtSearchBundle\Classes;


class PDOPool
{

  /**
   * @var PDOPool
   */
  private static $instance;

  /**
   * @var array
   */
  private $pool;

  private function __construct()
  {
    $this->pool = [];
  }

  /**
   * @return PDOPool
   */
  public static function getInstance(){
    if(static::$instance == null){
      static::$instance = new PDOPool();
    }
    return static::$instance;
  }

  /**
   * @param $dsn
   * @param $username
   * @param $password
   * @return \PDO
   */
  public function getHandler($dsn, $username, $password){
    if(isset($this->pool[$dsn . '__' . $username])){
      return $this->pool[$dsn . '__' . $username];
    }
    else{
      $pdo = new \PDO($dsn, $username, $password);
      print 'Adding new PDO connection for dsn "' . $dsn . '"' . PHP_EOL;
      $this->pool[$dsn . '__' . $username] = $pdo;
      return $pdo;
    }
  }

}