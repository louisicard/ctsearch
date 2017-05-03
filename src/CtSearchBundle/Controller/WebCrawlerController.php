<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\Classes\CurlUtils;
use CtSearchBundle\Datasource\WebCrawler;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\Classes\Processor;
use \Symfony\Component\HttpFoundation\Response;

class WebCrawlerController extends CtSearchController {

  /**
   * @Route("/webcrawler-response", name="webcrawler-response")
   */
  public function webCrawlerResponseAction(Request $request) {
    if($request->get('datasourceId') != null){
      $datasource = IndexManager::getInstance()->getDatasource($request->get('datasourceId'), $this);
      $datasource->handleDataFromCallback(array(
        'title' => $request->get('title') != null ? $request->get('title') : '',
        'html' => $request->get('html') != null ? $request->get('html') : '',
        'url' => $request->get('url') != null ? $request->get('url') : '',
      ));
      return new Response(json_encode(array('Status' => 'OK')), 200, array('Content-type' => 'text/html; charset=utf-8'));
    }
    else{
      return new Response(json_encode(array('Error' => 'No datasource provided')), 400, array('Content-type' => 'application/json'));
    }
  }

  /**
   * @Route("/webcrawler/test/{id}", name="webcrawler-test")
   */
  public function testCallbackAction(Request $request, $id) {
    /** @var WebCrawler $datasource */
    $datasource = IndexManager::getInstance()->getDatasource($id, $this);
    if($datasource != null) {
      if(get_class($datasource) == WebCrawler::class) {
        $form = $this->createFormBuilder(null)
          ->add('url', TextType::class, array(
            'label' => $this->get('translator')->trans('URL'),
            'required' => true,
          ))
          ->add('ok', SubmitType::class, array(
            'label' => $this->get('translator')->trans('Test "' . $datasource->getName() . '"')
          ))
          ->getForm();
        $form->handleRequest($request);
        if ($form->isValid()) {
          $data = $form->getData();
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $data['url']);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_HEADER, false);
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
          CurlUtils::handleCurlProxy($ch);
          $r = curl_exec($ch);
          $doc = new \DOMDocument();
          @$doc->loadHTML($r);
          $nodes = $doc->getElementsByTagName('title');
          $title = $nodes->item(0) ? $nodes->item(0)->nodeValue : null;
          $doc = array(
            'title' => $title,
            'url' => $data['url'],
            'html' => $r
          );
          ob_start();
          $datasource->handleDataFromCallback($doc);
          $output = ob_get_contents();
          ob_end_clean();
        }
      }
      else{
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Datasource must be a web crawler'));
      }
    }
    else{
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No datasource found for the ID you provided'));
    }
    $params = array(
      'title' => $this->get('translator')->trans('Data sources'),
      'main_menu_item' => 'datasources'
    );
    if(isset($form)){
      $params['form'] = $form->createView();
    }
    if(isset($output)){
      $params['output'] = $output;
    }
    return $this->render('ctsearch/datasource.html.twig', $params);
  }

}
