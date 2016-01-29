<?php

namespace CtSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use CtSearchBundle\Classes\Index;
use CtSearchBundle\Classes\Mapping;
use \Exception;

class DatasourceController extends Controller {

  /**
   * @Route("/datasources", name="datasources")
   */
  public function listDatasourcesAction(Request $request) {
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    $datasourceTypes = $indexManager->getDatasourceTypes();
    $form = $this->createFormBuilder(null)
      ->add('dataSourceType', 'choice', array(
        'choices' => array('' => $this->get('translator')->trans('Add a new datasource')) + $datasourceTypes,
        'required' => true,
      ))
      ->add('ok', 'submit', array(
        'label' => $this->get('translator')->trans('Add')
      ))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      $data = $form->getData();
      return $this->redirect($this->generateUrl('datasource-add', array('datasourceType' => $data['dataSourceType'])));
    }
    return $this->render('ctsearch/datasource.html.twig', array(
        'title' => $this->get('translator')->trans('Data sources'),
        'main_menu_item' => 'datasources',
        'datasources' => $indexManager->getDatasources($this),
        'form_add_datasource' => $form->createView()
    ));
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
        $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
        $indexManager->saveDatasource($form->getData());
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
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      $instance = $indexManager->getDatasource($request->get('id'), $this);
      $form = $instance->getSettingsForm()->getForm();
      $form->handleRequest($request);
      if ($form->isValid()) {
        $indexManager->saveDatasource($form->getData(), $request->get('id'));
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
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      $indexManager->deleteDatasource($request->get('id'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Datasource has been deleted'));
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
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      $instance = $indexManager->getDatasource($request->get('id'), $this);
      $form = $instance->getExcutionForm()->getForm();
      $form->handleRequest($request);
      if ($form->isValid()) {
        $instance->execute($form->getData());
      }
      return $this->render('ctsearch/datasource.html.twig', array(
          'title' => $this->get('translator')->trans('Execute "@ds_name"', array('@ds_name' => $instance->getName())),
          'main_menu_item' => 'datasources',
          'form' => $form->createView()
      ));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
      return $this->redirect($this->generateUrl('datasources'));
    }
  }
  
  /**
   * @Route("/datasources/testcallback", name="datasources-testcallback")
   */
  public function testCallbackAction(Request $request) {
    $form = $this->createFormBuilder(null)
      ->add('datasourceId', 'text', array(
        'required' => true,
      ))
      ->add('title', 'text', array(
        'required' => true,
      ))
      ->add('url', 'text', array(
        'required' => true,
      ))
      ->add('html', 'textarea', array(
        'required' => true,
      ))
      ->add('ok', 'submit', array(
        'label' => $this->get('translator')->trans('Test')
      ))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      $data = $form->getData();
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      $datasource = $indexManager->getDatasource($data['datasourceId'], $this);
      unset($data['datasourceId']);
      $datasource->handleDataFromCallback($data);
    }
    return $this->render('ctsearch/datasource.html.twig', array(
        'title' => $this->get('translator')->trans('Data sources'),
        'main_menu_item' => 'datasources',
        'form' => $form->createView(),
    ));
  }

}
