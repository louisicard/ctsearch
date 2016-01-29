<?php

namespace CtSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;

class ConsoleController extends Controller {

  /**
   * @Route("/console", name="console")
   */
  public function consoleAction(Request $request) {
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    $indexes = array_keys($indexManager->getElasticInfo());
    $choices = array("" => $this->get('translator')->trans('Select'));
    foreach ($indexes as $index) {
      $choices[$index] = $index;
    }
    $listener = function(\Symfony\Component\Form\FormEvent $event){
      $data = $event->getData();
      $data["searchQuery"] = json_encode(json_decode($data["searchQuery"]), JSON_PRETTY_PRINT);
      $event->setData($data);
    };
    $form = $this->createFormBuilder(null)
        ->add('index', 'choice', array(
          'label' => $this->get('translator')->trans('Index'),
          'choices' => $choices,
          'required' => true,
        ))
        ->add('searchQuery', 'textarea', array(
          'label' => $this->get('translator')->trans('Search query (JSON)'),
          'required' => true
        ))
        ->add('execute', 'submit', array('label' => $this->get('translator')->trans('Execute')))
        ->addEventListener(\Symfony\Component\Form\FormEvents::PRE_SUBMIT, $listener)
        ->getForm();
       
    $form->handleRequest($request);
    $params = array(
      'title' => $this->get('translator')->trans('Console'),
      'form' => $form->createView(),
      'main_menu_item' => 'console',
    );
    if ($form->isValid()) {
      $data = $form->getData();
      $query = $data['searchQuery'];
      $index = $data['index'];
      try{
        $res = $indexManager->search($index, $query);
        $params['results'] = $res;
      }
      catch(\Exception $ex){
        $params['exception'] = $ex;
      }
    }
    return $this->render('ctsearch/console.html.twig', $params);
  }

}
