<?php

namespace CtSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\Classes\Processor;
use \Symfony\Component\HttpFoundation\Response;

class ProcessorController extends Controller {

  /**
   * @Route("/processors", name="processors")
   */
  public function listProcessorsAction(Request $request) {
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
        
    $datasources = $indexManager->getDatasources($this);
    $indexes = $indexManager->getElasticInfo($this);
    $datasourceChoices = array();
    foreach ($datasources as $id => $datasource) {
      $datasourceChoices[$id] = $datasource->getName();
    }
    $targetChoices = array();
    foreach ($indexes as $indexName => $info) {
      $choices = array();
      if(isset($info['mappings'])){
        foreach ($info['mappings'] as $mapping) {
          $choices[$indexName . '.' . $mapping['name']] = $indexName . '.' . $mapping['name'];
        }
      }
      $targetChoices[$indexName] = $choices;
    }
    $form = $this->createFormBuilder(null)
      ->add('datasource', 'choice', array(
        'choices' => array('' => $this->get('translator')->trans('Select datasource')) + $datasourceChoices,
        'required' => true,
      ))
      ->add('target', 'choice', array(
        'choices' => array('' => $this->get('translator')->trans('Select a target')) + $targetChoices,
        'required' => true,
      ))
      ->add('ok', 'submit', array(
        'label' => $this->get('translator')->trans('Add')
      ))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      $data = $form->getData();
      return $this->redirect($this->generateUrl('processor-add', array('datasource' => $data['datasource'], 'target' => $data['target'])));
    }
    return $this->render('ctsearch/processor.html.twig', array(
        'title' => $this->get('translator')->trans('Processors'),
        'main_menu_item' => 'processors',
        'processors' => $indexManager->getRawProcessors(),
        'form_add_processor' => $form->createView()
    ));
  }

  /**
   * @Route("/processors/add", name="processor-add")
   */
  public function addProcessorAction(Request $request) {
    if ($request->get('datasource') != null && $request->get('target') != null) {
      return $this->handleAddOrEditProcessor($request);
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No datasource or target provided'));
      return $this->redirect($this->generateUrl('processors'));
    }
  }

  /**
   * @Route("/processors/edit", name="processor-edit")
   */
  public function editProcessorAction(Request $request) {
    if ($request->get('id')) {
      return $this->handleAddOrEditProcessor($request, $request->get('id'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No ID provided'));
      return $this->redirect($this->generateUrl('processors'));
    }
  }

  private function handleAddOrEditProcessor($request, $id = null) {
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    if($id == null){ //Add
      $datasource = $indexManager->getDatasource($request->get('datasource'), $this);
      $target = $request->get('target');
      $definition = array(
        'datasource' => array(
          'id' => $request->get('datasource'),
          'name' => $datasource->getName(),
          'fields' => $datasource->getFields(),
        ),
        'filters' => array(),
        'target' => $target,
      );
      $processor = new Processor();
      $processor->setDatasourceId($request->get('datasource'));
      $processor->setTarget($request->get('target'));
      $processor->setDefinition(json_encode($definition, JSON_PRETTY_PRINT));
    }
    else{ //Edit
      $processor = $indexManager->getProcessor($id);
      $datasource = $indexManager->getDatasource($processor->getDatasourceId(), $this);
    }
    $form = $this->createFormBuilder($processor)
      ->add('datasourceName', 'text', array(
        'label' => $this->get('translator')->trans('Datasource'),
        'data' => $datasource->getName(),
        'read_only' => true,
        'disabled' => true,
        'required' => true,
        'mapped' => false
      ))
      ->add('target', 'text', array(
        'label' => $this->get('translator')->trans('Target'),
        'read_only' => true,
        'disabled' => true,
        'required' => true
      ))
      ->add('definition', 'textarea', array(
        'label' => $this->get('translator')->trans('JSON Definition'),
        'required' => true
      ))
      ->add('save', 'submit', array('label' => $this->get('translator')->trans('Save')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      $indexManager->saveProcessor($form->getData(), $id);
      if($id == null){
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('New processor has been added successfully'));
      }
      else{
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Processor has been updated successfully'));
      }
      if($id == null)
        return $this->redirect($this->generateUrl('processors'));
    }
    $target_r = explode('.', $processor->getTarget());
    $indexName = $target_r[0];
    $mappingName = $target_r[1];
    $mapping = $indexManager->getMapping($indexName, $mappingName);
    if($mapping !=null)
      $targetFields = array_keys(json_decode($mapping->getMappingDefinition(), TRUE));
    else
      $targetFields = array();
    return $this->render('ctsearch/processor.html.twig', array(
        'title' => $id == null ? $this->get('translator')->trans('New processor') : $this->get('translator')->trans('Edit processor'),
        'main_menu_item' => 'processors',
        'filterTypes' => $indexManager->getFilterTypes(),
        'form' => $form->createView(),
        'targetFields' => $targetFields,
        'mappingName' => $mappingName,
        'datasourceFields' => $datasource->getFields(),
    ));
  }

  /**
   * @Route("/processor/delete", name="processor-delete")
   */
  public function deleteProcessorAction(Request $request) {
    if ($request->get('id') != null) {
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      $indexManager->deleteProcessor($request->get('id'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Processor has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('processors'));
  }


  /**
   * @Route("/processors/get-settings-form", name="get-settings-form")
   */
  public function getSettingsFormAction(Request $request) {
    if ($request->get('class') != null) {
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      $class = $request->get('class');
      $data = $request->get('data') != null ? json_decode($request->get('data'), true) : array();
      $filter = new $class($data, $indexManager);
      $form = $filter->getSettingsForm($this)->getForm();
      $form->handleRequest($request);
      if ($form->isValid()) {
        $data = $form->getData();
        $filter->setData($data);
        $response = array(
          'class' => $class,
          'filterDisplayName' => $filter->getDisplayName(),
          'settings' => $filter->getSettings(),
          'arguments' => $filter->getArgumentsData(),
          'inStackName' => $filter->getInStackName(),
          'autoImplode' => $filter->getAutoImplode(),
          'autoImplodeSeparator' => $filter->getAutoImplodeSeparator(),
          'autoStriptags' => $filter->getAutoStriptags(),
          'isHTML' => $filter->getIsHTML(),
          'fields' => $filter->getFields(),
        );
        return new Response(json_encode($response), 200, array('Content-type' => 'application/json'));
      }
      return $this->render('ctsearch/ajaxform.html.twig', array(
          'form' => $form->createView()
      ));
    }
  }

}
