<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 06/03/2017
 * Time: 21:12
 */

namespace CtSearchBundle\Classes;


use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
  public function loadUserByUsername($username)
  {
    $user = IndexManager::getInstance()->getUser($username);
    if($user == null){
      throw new UsernameNotFoundException();
    }
    return $user;
  }

  public function refreshUser(UserInterface $user)
  {
    return IndexManager::getInstance()->getUser($user->getUsername());
  }

  public function supportsClass($class)
  {
    return $class == User::class;
  }

}