<?php

namespace CtSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;

class HomeController extends Controller {

  /**
   * @Route("/", name="homepage")
   */
  public function indexAction(Request $request) {
    
    $info = IndexManager::getInstance()->getElasticInfo();
    
    $serverInfo = IndexManager::getInstance()->getServerInfo();

    return $this->render('ctsearch/homepage.html.twig', array(
          'title' => $this->get('translator')->trans('Welcome to Ct search'),
          'info' => $info,
          'server_info' => $serverInfo,
          'main_menu_item' => 'home',
    ));
  }

}
