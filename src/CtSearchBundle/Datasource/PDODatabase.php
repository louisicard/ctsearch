<?php

namespace CtSearchBundle\Datasource;

use \CtSearchBundle\CtSearchBundle;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PDODatabase extends Datasource
{

  private $driver;
  private $host;
  private $port;
  private $dbName;
  private $username;
  private $password;
  private $batchSize;
  private $sql;

  public function getSettings()
  {
    return array(
      'driver' => $this->getDriver() != null ? $this->getDriver() : '',
      'host' => $this->getHost() != null ? $this->getHost() : '',
      'port' => $this->getPort() != null ? $this->getPort() : '',
      'dbName' => $this->getDbName() != null ? $this->getDbName() : '',
      'username' => $this->getUsername() != null ? $this->getUsername() : '',
      'password' => $this->getPassword() != null ? $this->getPassword() : '',
      'batchSize' => $this->getBatchSize() != null ? $this->getBatchSize() : '',
      'sql' => $this->getSql() != null ? $this->getSql() : '',
    );
  }

  public function initFromSettings($settings)
  {
    foreach ($settings as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function execute($execParams = null)
  {
    try {
      $tries_dsn = 0;
      $retry_dsn = true;
      while ($tries_dsn == 0 || $retry_dsn) {
        try {
          $count = 0;
          $dsn = $this->getDriver() . ':host=' . $this->getHost() . ';port=' . $this->getPort() . ';dbname=' . $this->getDbName() . ';charset=UTF8;';
          $pdo = new \PDO($dsn, $this->getUsername(), $this->getPassword());

          $continue = true;
          $offset = 0;
          while ($continue) {
            $sql = $this->getSql();
            $sql = str_replace('@limit', $this->getBatchSize(), $sql);
            $sql = str_replace('@offset', $offset, $sql);
            if ($this->getOutput() != null) {
              $this->getOutput()->writeln('Executing SQL: ' . $sql);
            }
            $tries = 0;
            $retry = true;
            while ($tries == 0 || $retry) {
              try {
                $res = $pdo->query($sql);
                $continue = false;
                while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
                  $continue = $this->hasPagination();
                  $count++;
                  $this->index(array(
                    'row' => $row
                  ));
                  if ($this->getOutput() != null) {
                    //$this->getOutput()->writeln('Indexing document ' . $count);
                  }
                }
                $offset += $this->getBatchSize();
                $retry = false;
              } catch (\PDOException $ex) {
                print get_class($this) . ' >> PDO Exception has been caught (' . $ex->getMessage() . ')' . PHP_EOL;
                if ($tries > 20) {
                  $retry = false;
                  print get_class($this) . ' >> This is over, I choose to die.' . PHP_EOL;
                  return; //Kill the datasource
                } else {
                  print get_class($this) . ' >> Retrying in 1 second...' . PHP_EOL;
                  sleep(1); //Sleep for 1 second
                }
              } finally {
                $tries++;
              }
            }
          }
          $retry_dsn = false;
        } catch (\PDOException $ex) {
          print get_class($this) . ' >> PDO Exception has been caught (' . $ex->getMessage() . ')' . PHP_EOL;
          if ($tries_dsn > 20) {
            $retry_dsn = false;
            print get_class($this) . ' >> This is over, I choose to die.' . PHP_EOL;
            throw $ex;
          } else {
            print get_class($this) . ' >> Retrying in 1 second...' . PHP_EOL;
            sleep(1); //Sleep for 1 second
          }
        } finally {
          $tries_dsn++;
        }
      }
    } catch (Exception $ex) {
      print $ex->getMessage();
    }

    if ($this->getController() != null) {
      CtSearchBundle::addSessionMessage($this->getController(), 'status', 'Found ' . $count . ' documents');
    }
    parent::execute($execParams);
  }

  private function hasPagination()
  {
    $sql = $this->getSql();
    return strpos($sql, '@limit') !== FALSE && strpos($sql, '@offset') !== FALSE;
  }

  public function getSettingsForm()
  {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder
        ->add('driver', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('PDO driver (E.g.: mysql, postgresl)'),
          'required' => true
        ))
        ->add('host', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Host'),
          'required' => true
        ))
        ->add('port', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Port'),
          'required' => true
        ))
        ->add('dbName', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Database name'),
          'required' => true
        ))
        ->add('username', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Username'),
          'required' => true
        ))
        ->add('password', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Password'),
          'required' => true
        ))
        ->add('batchSize', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('Batch size (used in limit statement)'),
          'required' => true
        ))
        ->add('sql', TextareaType::class, array(
          'label' => $this->getController()->get('translator')->trans('SQL query (!! use @limit and @offset variables for pagination !!)'),
          'required' => true
        ))
        ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Save')));
      return $formBuilder;
    } else {
      return null;
    }
  }

  public function getExcutionForm()
  {
    $formBuilder = $this->getController()->createFormBuilder()
      ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getDatasourceDisplayName()
  {
    return 'PDO Database';
  }

  public function getFields()
  {
    return array(
      'row'
    );
  }

  /**
   * @return mixed
   */
  public function getDriver()
  {
    return $this->driver;
  }

  /**
   * @param mixed $driver
   */
  public function setDriver($driver)
  {
    $this->driver = $driver;
  }

  /**
   * @return mixed
   */
  public function getHost()
  {
    return $this->host;
  }

  /**
   * @param mixed $host
   */
  public function setHost($host)
  {
    $this->host = $host;
  }

  /**
   * @return mixed
   */
  public function getPort()
  {
    return $this->port;
  }

  /**
   * @param mixed $port
   */
  public function setPort($port)
  {
    $this->port = $port;
  }

  /**
   * @return mixed
   */
  public function getDbName()
  {
    return $this->dbName;
  }

  /**
   * @param mixed $dbName
   */
  public function setDbName($dbName)
  {
    $this->dbName = $dbName;
  }

  /**
   * @return mixed
   */
  public function getUsername()
  {
    return $this->username;
  }

  /**
   * @param mixed $username
   */
  public function setUsername($username)
  {
    $this->username = $username;
  }

  /**
   * @return mixed
   */
  public function getPassword()
  {
    return $this->password;
  }

  /**
   * @param mixed $password
   */
  public function setPassword($password)
  {
    $this->password = $password;
  }

  /**
   * @return mixed
   */
  public function getSql()
  {
    return $this->sql;
  }

  /**
   * @param mixed $sql
   */
  public function setSql($sql)
  {
    $this->sql = $sql;
  }

  /**
   * @return mixed
   */
  public function getBatchSize()
  {
    return $this->batchSize;
  }

  /**
   * @param mixed $batchSize
   */
  public function setBatchSize($batchSize)
  {
    $this->batchSize = $batchSize;
  }


}
