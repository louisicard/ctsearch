<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 06/03/2017
 * Time: 20:42
 */

namespace CtSearchBundle\Classes;


use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{

  /**
   * @var string
   */
  private $uid;
  /**
   * @var string
   */
  private $password;
  /**
   * @var array
   */
  private $roles;
  /**
   * @var string
   */
  private $email;
  /**
   * @var string
   */
  private $fullName;
  /**
   * @var array
   */
  private $groups;

  /**
   * User constructor.
   * @param string $uid
   * @param array $roles
   * @param string $email
   * @param string $fullName
   * @param array $groups
   */
  public function __construct($uid, array $roles, $email, $fullName, array $groups)
  {
    $this->uid = $uid;
    $this->roles = $roles;
    $this->email = $email;
    $this->fullName = $fullName;
    $this->groups = $groups;
  }

  /**
   * @param string $uid
   */
  public function setUid($uid)
  {
    $this->uid = $uid;
  }

  /**
   * @param string $password
   */
  public function setPassword($password)
  {
    $this->password = $password;
  }

  /**
   * @param array $roles
   */
  public function setRoles($roles)
  {
    $this->roles = $roles;
  }

  /**
   * @param string $email
   */
  public function setEmail($email)
  {
    $this->email = $email;
  }

  /**
   * @param string $fullName
   */
  public function setFullName($fullName)
  {
    $this->fullName = $fullName;
  }

  /**
   * @param array $groups
   */
  public function setGroups($groups)
  {
    $this->groups = $groups;
  }

  /**
   * @return string
   */
  public function getUid()
  {
    return $this->uid;
  }

  /**
   * @return string
   */
  public function getEmail()
  {
    return $this->email;
  }

  /**
   * @return string
   */
  public function getFullName()
  {
    return $this->fullName;
  }

  /**
   * @return array
   */
  public function getGroups()
  {
    return $this->groups;
  }

  public function getRoles()
  {
    return $this->roles;
  }

  public function getPassword()
  {
    return $this->password;
  }

  public function getSalt()
  {
    return '';
  }

  public function getUsername()
  {
    return $this->uid;
  }

  public function eraseCredentials()
  {

  }

}