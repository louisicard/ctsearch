<?php

namespace CtSearchBundle\Controller;

use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;

class HomeController extends Controller {

  /**
   * @Route("/", name="homepage")
   */
  public function indexAction(Request $request) {
    try {

      $info = IndexManager::getInstance()->getElasticInfo();

      $serverInfo = IndexManager::getInstance()->getServerInfo();
    }
    catch(NoNodesAvailableException $ex){
      $info = null;
      $serverInfo = null;
      $noMenu = true;
    }

    return $this->render('ctsearch/homepage.html.twig', array(
          'title' => $this->get('translator')->trans('Welcome to Ct search'),
          'info' => $info,
          'server_info' => $serverInfo,
          'main_menu_item' => 'home',
          'no_menu' => isset($noMenu) && $noMenu ? true : false,
    ));
  }

}
