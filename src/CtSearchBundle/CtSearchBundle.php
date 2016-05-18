<?php

namespace CtSearchBundle;

use CtSearchBundle\DependencyInjection\CtSearchCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CtSearchBundle extends Bundle {


  private static $session = null;
  /**
   * 
   * @param Controller $controller
   * @param string $type
   * @param string $message
   */
  static function addSessionMessage($controller, $type, $message) {
    if (CtSearchBundle::$session == null)
      CtSearchBundle::$session = new Session();
    if (CtSearchBundle::$session->get('messages') != null) {
      $messages = CtSearchBundle::$session->get('messages');
      $messages[] = array(
        'type' => is_object($message) || is_array($message) ? 'object' : $type,
        'text' => is_object($message) || is_array($message) ? \Krumo::dump($message, KRUMO_RETURN) : $message,
      );
    } else {
      $messages = array(
        array(
          'type' => is_object($message) || is_array($message) ? 'object' : $type,
          'text' => is_object($message) || is_array($message) ? \Krumo::dump($message, KRUMO_RETURN) : $message,
        )
      );
    }
    CtSearchBundle::$session->set('messages', $messages);
  }

  static function resetMessages(){
    if (CtSearchBundle::$session == null)
      CtSearchBundle::$session = new Session();
    CtSearchBundle::$session->set('messages', null);
  }

  public function build(ContainerBuilder $container)
  {
    parent::build($container);

    $container->addCompilerPass(new CtSearchCompilerPass());
  }
}
