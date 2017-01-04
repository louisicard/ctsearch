<?php

namespace CtSearchBundle\Datasource;

use \CtSearchBundle\CtSearchBundle;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PDBV1Database extends Datasource
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
      'batchSize' => $this->getBatchSize() != null ? $this->getBatchSize() : ''
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
      $count = 0;
      $dsn = $this->getDriver() . ':host=' . $this->getHost() . ';port=' . $this->getPort() . ';dbname=' . $this->getDbName() . ';charset=UTF8;';
      $pdo = new \PDO($dsn, $this->getUsername(), $this->getPassword());

      $storeRs = $pdo->query("SELECT * FROM store ORDER BY uid ASC");
      while($store = $storeRs->fetch(\PDO::FETCH_ASSOC)) {
        if ($this->getOutput() != null) {
          $this->getOutput()->writeln('Harvesting database for store #' . $store['uid'] . ' "' . $store['label'] . '"');
        }
        $continue = true;
        $offset = 0;
        while ($continue) {
          $sql = "SELECT
                    product.uid,
                    product.label,
                    product.long_label,
                    product.short_label,
                    product.vat_code,
                    product.weight,
                    category_" . $store['uid'] . ".id as category_id,
                    category_" . $store['uid'] . ".label as category_label,
                    product_range_" . $store['uid'] . ".label as range_label,
                    product_range_" . $store['uid'] . ".long_description as range_description,
                    product_store.store_uid,
                    store.label AS store_name,
                    product_store.stock
                  FROM
                    product
                    INNER JOIN product_store ON product_store.product_uid=product.uid
                    INNER JOIN store ON store.uid=product_store.store_uid AND store.uid=" . $store['uid'] . "
                    INNER JOIN product_range_" . $store['uid'] . " ON product_range_" . $store['uid'] . ".id=product.range_id
                    INNER JOIN category_" . $store['uid'] . " ON category_" . $store['uid'] . ".id=product_range_" . $store['uid'] . ".category_id
                  WHERE
                    product.type IS NULL
                    AND product_store.is_enabled=1
                  ORDER BY product.uid ASC
                  LIMIT @offset,@limit;";
          $sql = str_replace('@limit', $this->getBatchSize(), $sql);
          $sql = str_replace('@offset', $offset, $sql);
          $res = $pdo->query($sql);
          $continue = false;
          while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
            $continue = true;
            $count++;
            $this->index(array(
              'row' => $row
            ));
          }
          $offset += $this->getBatchSize();
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

  public function getSettingsForm()
  {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder
        ->add('driver', TextType::class, array(
          'label' => $this->getController()->get('translator')->trans('PD driver (E.g.: mysql, postgresl)'),
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
    return 'PDB V1 Database';
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
