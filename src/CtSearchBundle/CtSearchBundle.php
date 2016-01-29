<?php

namespace CtSearchBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CtSearchBundle extends Bundle {

  /**
   * 
   * @param Controller $controller
   * @param string $type
   * @param string $message
   */
  static function addSessionMessage($controller, $type, $message) {
    if ($controller != null) {
      if ($controller->get('session')->get('messages') != null) {
        $messages = $controller->get('session')->get('messages');
        $messages[] = array(
          'type' => $type,
          'text' => $message,
        );
      } else {
        $messages = array(
          array(
            'type' => $type,
            'text' => $message,
          )
        );
      }
      $controller->get('session')->set('messages', $messages);
    }
  }

}
