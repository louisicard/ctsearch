<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\Datasource\Datasource;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\Classes\Processor;
use \Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProcessorController extends CtSearchController
{

  /**
   * @Route("/processors", name="processors")
   */
  public function listProcessorsAction(Request $request)
  {

    $datasources = IndexManager::getInstance()->getDatasources($this);
    $indexes = IndexManager::getInstance()->getElasticInfo($this);
    $datasourceChoices = array();
    foreach ($datasources as $datasource) {
      $datasourceChoices[$datasource->getName()] = $datasource->getId();
    }
    ksort($datasourceChoices);
    $targetChoices = array();
    foreach ($indexes as $indexName => $info) {
      $choices = array();
      if (isset($info['mappings'])) {
        foreach ($info['mappings'] as $mapping) {
          $choices[$indexName . '.' . $mapping['name']] = $indexName . '.' . $mapping['name'];
        }
      }
      $targetChoices[$indexName] = $choices;
    }
    ksort($targetChoices);
    $form = $this->createFormBuilder(null)
      ->add('datasource', ChoiceType::class, array(
        'choices' => array($this->get('translator')->trans('Select datasource') => '') + $datasourceChoices,
        'required' => true,
      ))
      ->add('target', ChoiceType::class, array(
        'choices' => array($this->get('translator')->trans('Select a target') => '') + $targetChoices,
        'required' => true,
      ))
      ->add('ok', SubmitType::class, array(
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
      'processors' => IndexManager::getInstance()->getRawProcessors(),
      'form_add_processor' => $form->createView()
    ));
  }

  /**
   * @Route("/processors/add", name="processor-add")
   */
  public function addProcessorAction(Request $request)
  {
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
  public function editProcessorAction(Request $request)
  {
    if ($request->get('id')) {
      return $this->handleAddOrEditProcessor($request, $request->get('id'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No ID provided'));
      return $this->redirect($this->generateUrl('processors'));
    }
  }

  private function handleAddOrEditProcessor($request, $id = null)
  {
    if ($id == null) { //Add
      $datasource = IndexManager::getInstance()->getDatasource($request->get('datasource'), $this);
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
    } else { //Edit
      $processor = IndexManager::getInstance()->getProcessor($id);
      $datasource = IndexManager::getInstance()->getDatasource($processor->getDatasourceId(), $this);
    }
    if(is_array($processor->getTargetSiblings())){
      $processor->setTargetSiblings(implode(',', $processor->getTargetSiblings()));
    }
    $form = $this->createFormBuilder($processor)
      ->add('datasourceName', TextType::class, array(
        'label' => $this->get('translator')->trans('Datasource'),
        'data' => $datasource->getName(),
        'disabled' => true,
        'required' => true,
        'mapped' => false
      ))
      ->add('target', TextType::class, array(
        'label' => $this->get('translator')->trans('Target'),
        'disabled' => true,
        'required' => true
      ))
      ->add('targetSiblings', HiddenType::class, array())
      ->add('definition', TextareaType::class, array(
        'label' => $this->get('translator')->trans('JSON Definition'),
        'required' => true
      ))
      ->add('save', SubmitType::class, array('label' => $this->get('translator')->trans('Save')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      /** @var Processor $proc */
      $proc = $form->getData();
      if($proc->getTargetSiblings() != ''){
        $proc->setTargetSiblings(explode(',', $proc->getTargetSiblings()));
      }
      IndexManager::getInstance()->saveProcessor($proc, $id);
      if ($id == null) {
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('New processor has been added successfully'));
      } else {
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Processor has been updated successfully'));
      }
      if ($id == null)
        return $this->redirect($this->generateUrl('processors'));
    }
    $target_r = explode('.', $processor->getTarget());
    $indexName = $target_r[0];
    $mappingName = $target_r[1];
    $mapping = IndexManager::getInstance()->getMapping($indexName, $mappingName);
    if ($mapping != null)
      $targetFields = array_keys(json_decode($mapping->getMappingDefinition(), TRUE));
    else
      $targetFields = array();
    $filterTypes = IndexManager::getInstance()->getFilterTypes($this->container);
    asort($filterTypes);
    return $this->render('ctsearch/processor.html.twig', array(
      'title' => $id == null ? $this->get('translator')->trans('New processor') : $this->get('translator')->trans('Edit processor'),
      'main_menu_item' => 'processors',
      'filterTypes' => $filterTypes,
      'form' => $form->createView(),
      'targetFields' => $targetFields,
      'mappingName' => $mappingName,
      'datasourceId' => $processor->getDatasourceId(),
      'datasourceFields' => $datasource->getFields(),
    ));
  }

  /**
   * @Route("/processor/delete", name="processor-delete")
   */
  public function deleteProcessorAction(Request $request)
  {
    if ($request->get('id') != null) {
      IndexManager::getInstance()->deleteProcessor($request->get('id'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Processor has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('processors'));
  }

  /**
   * @Route("/processors/get-settings-form", name="get-settings-form")
   */
  public function getSettingsFormAction(Request $request)
  {
    if ($request->get('class') != null) {
      $class = $request->get('class');
      $data = $request->get('data') != null ? json_decode($request->get('data'), true) : array();
      $filter = new $class($data, IndexManager::getInstance());
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

  /**
   * @Route("/processors/export", name="processor-export")
   */
  public function exportProcessorAction(Request $request)
  {
    if ($request->get('id')) {
      $proc = IndexManager::getInstance()->getProcessor($request->get('id'));
      if ($proc != null) {
        return new Response($proc->export(), 200, array('Content-type' => 'application/json;charset=utf-8', 'Content-disposition' => 'attachment;filename=processor_' . $proc->getTarget() . '.json'));
      } else {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No processor found for this id'));
        return $this->redirect($this->generateUrl('processors'));
      }
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No ID provided'));
      return $this->redirect($this->generateUrl('processors'));
    }
  }

  /**
   * @Route("/processors/import", name="processor-import")
   */
  public function importProcessorAction(Request $request)
  {
    $form = $this->createFormBuilder()
      ->add('file', FileType::class, array(
        'label' => $this->get('translator')->trans('File'),
        'required' => true,
      ))
      ->add('override', CheckboxType::class, array(
        'label' => $this->get('translator')->trans('Override existing Index/Mapping/Datasource/Processor'),
        'required' => false
      ))
      ->add('import', SubmitType::class, array('label' => $this->get('translator')->trans('Import')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      $file = $form->getData()['file'];
      /* @var $file Symfony\Component\HttpFoundation\File\UploadedFile */
      $json = json_decode(file_get_contents($file->getRealPath()), true);
      $override = $form->getData()['override'];
      Processor::import($json, $override);
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Processor has been imported'));
      return $this->redirect($this->generateUrl('processor-import'));
    }
    return $this->render('ctsearch/processor.html.twig', array(
      'title' => $this->get('translator')->trans('Import'),
      'main_menu_item' => 'processors',
      'import_form' => $form->createView(),
    ));
  }

}
