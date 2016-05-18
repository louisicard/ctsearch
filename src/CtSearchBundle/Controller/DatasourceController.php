<?php

namespace CtSearchBundle\Controller;

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

class DatasourceController extends CtSearchController {

  /**
   * @Route("/datasources", name="datasources")
   */
  public function listDatasourcesAction(Request $request) {
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
    return $this->render('ctsearch/datasource.html.twig', array(
        'title' => $this->get('translator')->trans('Data sources'),
        'main_menu_item' => 'datasources',
        'datasources' => IndexManager::getInstance()->getDatasources($this),
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
      $instance = IndexManager::getInstance()->getDatasource($request->get('id'), $this);
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
   * @Route("/datasources/exec", name="datasource-exec")
   */
  public function executeDatasourceAction(Request $request) {
    if ($request->get('id') != null) {
      $instance = IndexManager::getInstance()->getDatasource($request->get('id'), $this);
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
      ->add('datasourceId', TextType::class, array(
        'required' => true,
      ))
      ->add('title', TextType::class, array(
        'required' => true,
      ))
      ->add('url', TextType::class, array(
        'required' => true,
      ))
      ->add('html', TextareaType::class, array(
        'required' => true,
      ))
      ->add('ok', SubmitType::class, array(
        'label' => $this->get('translator')->trans('Test')
      ))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      $data = $form->getData();
      $datasource = IndexManager::getInstance()->getDatasource($data['datasourceId'], $this);
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
