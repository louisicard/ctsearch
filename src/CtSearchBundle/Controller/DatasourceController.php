<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\Datasource\Datasource;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use CtSearchBundle\Classes\Index;
use CtSearchBundle\Classes\Mapping;
use \Exception;
use Symfony\Component\HttpFoundation\Response;

class DatasourceController extends CtSearchController {

  /**
   * @Route("/datasources", name="datasources")
   */
  public function listDatasourcesAction(Request $request) {
    $procs = Datasource::getRunningDatasources();
    $datasourceTypes = IndexManager::getInstance()->getDatasourceTypes($this->container);
    asort($datasourceTypes);
    $form = $this->createFormBuilder(null)
      ->add('dataSourceType', ChoiceType::class, array(
        'choices' => array($this->get('translator')->trans('Add a new datasource') => '') + $datasourceTypes,
        'required' => true,
      ))
      ->add('ok', SubmitType::class, array(
        'label' => $this->get('translator')->trans('Add')
      ))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      $data = $form->getData();
      return $this->redirect($this->generateUrl('datasource-add', array('datasourceType' => $data['dataSourceType'])));
    }
    $datasources = IndexManager::getInstance()->getDatasources($this);
    foreach($datasources as $datasource){
      $datasource->class = get_class($datasource);//For the twig template
    }
    return $this->render('ctsearch/datasource.html.twig', array(
      'title' => $this->get('translator')->trans('Data sources'),
      'main_menu_item' => 'datasources',
      'datasources' => $datasources,
      'form_add_datasource' => $form->createView(),
      'procs' => $procs
    ));
  }

  /**
   * @Route("/datasources/ajaxlist", name="datasources_ajaxlist")
   */
  public function ajaxListDatasourcesAction(Request $request) {
    $datasources = IndexManager::getInstance()->getDatasources($this);
    $r = [];
    foreach($datasources as $datasource){
      /** @var Datasource $datasource */
      $r[] = array(
        'id' => $datasource->getId(),
        'name' => $datasource->getName(),
        'class' => get_class($datasource)
      );
    }
    return new Response(json_encode($r, JSON_PRETTY_PRINT), 200, array('Content-type' => 'application/json; charset=utf-8'));
  }

  /**
   * @Route("/datasources/add", name="datasource-add")
   */
  public function addDatasourceAction(Request $request) {
    if ($request->get('datasourceType') != null) {
      $datasourceType = $request->get('datasourceType');
      $instance = new $datasourceType('', $this);
      $form = $instance->getSettingsForm()->getForm();
      $form->handleRequest($request);
      if ($form->isValid()) {
        IndexManager::getInstance()->saveDatasource($form->getData());
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Datasource has been added'));
        return $this->redirect($this->generateUrl('datasources'));
      }
      return $this->render('ctsearch/datasource.html.twig', array(
          'title' => $this->get('translator')->trans('New datasource'),
          'main_menu_item' => 'datasources',
          'form' => $form->createView()
      ));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No datasource type provided'));
      return $this->redirect($this->generateUrl('datasources'));
    }
  }

  /**
   * @Route("/datasources/edit", name="datasource-edit")
   */
  public function editDatasourceAction(Request $request) {
    if ($request->get('id') != null) {
      $instance = IndexManager::getInstance()->getDatasource($request->get('id'), $this, TRUE);
      $form = $instance->getSettingsForm()->getForm();
      $form->handleRequest($request);
      if ($form->isValid()) {
        IndexManager::getInstance()->saveDatasource($form->getData(), $request->get('id'));
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Datasource has been updated'));
        return $this->redirect($this->generateUrl('datasources'));
      }
      return $this->render('ctsearch/datasource.html.twig', array(
          'title' => $this->get('translator')->trans('Edit datasource'),
          'main_menu_item' => 'datasources',
          'form' => $form->createView()
      ));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
      return $this->redirect($this->generateUrl('datasources'));
    }
  }

  /**
   * @Route("/datasources/delete", name="datasource-delete")
   */
  public function deleteDatasourceAction(Request $request) {
    if ($request->get('id') != null) {
      IndexManager::getInstance()->deleteDatasource($request->get('id'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Datasource has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('datasources'));
  }

  /**
   * @Route("/datasources/kill", name="datasource-kill")
   */
  public function killDatasourceAction(Request $request) {
    if ($request->get('id') != null) {
      $instance = IndexManager::getInstance()->getDatasource($request->get('id'), $this);
      $instance->kill();
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Datasource has been killed'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('datasources'));
  }

  /**
   * @Route("/datasources/exec", name="datasource-exec")
   */
  public function executeDatasourceAction(Request $request) {
    if ($request->get('id') != null) {
      $instance = IndexManager::getInstance()->getDatasource($request->get('id'), $this);
      $procs = Datasource::getRunningDatasources();
      if(isset($procs[$instance->getId()])){
        return $this->render('ctsearch/datasource.html.twig', array(
          'title' => $this->get('translator')->trans('Monitor "@ds_name"', array('@ds_name' => $instance->getName())),
          'main_menu_item' => 'datasources',
          'proc' => $procs[$instance->getId()],
          'datasource' => $instance
        ));
      }
      else {
        $form = $instance->getExcutionForm()->getForm();
        $form->handleRequest($request);
        if ($form->isValid()) {
          $instance->launchProcess($form->getData());
          CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Datasource has been launched'));
          return $this->redirect($this->generateUrl('datasources'));
        }
        return $this->render('ctsearch/datasource.html.twig', array(
          'title' => $this->get('translator')->trans('Execute "@ds_name"', array('@ds_name' => $instance->getName())),
          'main_menu_item' => 'datasources',
          'form' => $form->createView()
        ));
      }
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
      return $this->redirect($this->generateUrl('datasources'));
    }
  }

  /**
   * @Route("/datasources/output", name="datasource-get-output")
   */
  public function getDatasourceOutputAction(Request $request) {
    $datasource = IndexManager::getInstance()->getDatasource($request->get('id'), $this);
    $output = $datasource->getOutputContent($request->get('from'));
    return new Response($output, 200, array('Content-Type' => 'text/plain; charset=utf-8'));
  }

}
