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

class IndexController extends Controller {

  /**
   * @Route("/indexes", name="indexes")
   */
  public function listIndexesAction(Request $request) {
    // replace this example code with whatever you need
    //var_dump($this->container->getParameter('ct_search.es_url'));
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    $info = $indexManager->getElasticInfo();
    return $this->render('ctsearch/indexes.html.twig', array(
        'title' => $this->get('translator')->trans('Indexes'),
        'main_menu_item' => 'indexes',
        'indexes' => $info,
    ));
  }

  /**
   * @Route("/indexes/add", name="index-add")
   */
  public function addIndexAction(Request $request) {
    return $this->getIndexForm($request, $this->container->getParameter('ct_search.es_url'), true);
  }

  /**
   * @Route("/indexes/edit", name="index-edit")
   */
  public function editIndexAction(Request $request) {
    if ($request->get('index_name') != null) {
      return $this->getIndexForm($request, $this->container->getParameter('ct_search.es_url'), false);
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No index provided'));
      return $this->redirect($this->generateUrl('indexes'));
    }
  }

  /**
   * @Route("/indexes/delete", name="index-delete")
   */
  public function deleteIndexAction(Request $request) {
    if ($request->get('index_name') != null) {
      $index = new Index($request->get('index_name'));
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      $indexManager->deleteIndex($index);
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Index has been deleted'));
      return $this->redirect($this->generateUrl('indexes'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No index provided'));
      return $this->redirect($this->generateUrl('indexes'));
    }
  }

  /**
   * @Route("/indexes/edit-mapping", name="index-edit-mapping")
   */
  public function editMappingAction(Request $request) {
    if ($request->get('index_name') != null && $request->get('mapping_name') != null) {
      return $this->getMappingForm($request, false);
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No index or mapping provided'));
      return $this->redirect($this->generateUrl('indexes'));
    }
  }

  /**
   * @Route("/indexes/add-mapping", name="index-add-mapping")
   */
  public function addMappingAction(Request $request) {
    if ($request->get('index_name') != null) {
      return $this->getMappingForm($request, true);
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No index or mapping provided'));
      return $this->redirect($this->generateUrl('indexes'));
    }
  }

  private function getIndexForm($request, $esUrl, $add) {
    $indexManager = new IndexManager($esUrl);
    if ($add) {
      $index = new Index();
    } else {
      $index = $indexManager->getIndex($request->get('index_name'));
    }
    $form = $this->createFormBuilder($index)
      ->add('indexName', 'text', array(
        'label' => $this->get('translator')->trans('Index name'),
        'read_only' => !$add,
        'disabled' => !$add,
        'required' => true
      ))
      ->add('settings', 'textarea', array(
        'label' => $this->get('translator')->trans('Settings (JSON syntax)'),
      ))
      ->add('create', 'submit', array('label' => $add ? $this->get('translator')->trans('Create index') : $this->get('translator')->trans('Update index')))
      ->getForm();

    $form->handleRequest($request);

    if ($form->isValid()) {
      $index = $form->getData();
      try {
        if ($add) {
          $indexManager->createIndex($index);
          CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Index has been created'));
        } else {
          $indexManager->updateIndex($index);
          CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Index has been updated'));
        }
        return $this->redirect($this->generateUrl('indexes'));
      } catch (Exception $ex) {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('An error as occured: ') . $ex->getMessage());
      }
    }

    return $this->render('ctsearch/indexes.html.twig', array(
        'title' => $add ? $this->get('translator')->trans('Add an index') : $this->get('translator')->trans('Edit index settings'),
        'main_menu_item' => 'indexes',
        'form' => $form->createView(),
    ));
  }

  private function getMappingForm($request, $add) {
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    if ($add) {
      $mapping = new Mapping($request->get('index_name'), '');
    } else {
      $mapping = $indexManager->getMapping($request->get('index_name'), $request->get('mapping_name'));
    }
    $analyzers = $indexManager->getAnalyzers($request->get('index_name'));
    $fieldTypes = $indexManager->getFieldTypes();
    $dateFormats = $indexManager->getDateFormats();
    $form = $this->createFormBuilder($mapping)
      ->add('indexName', 'text', array(
        'label' => $this->get('translator')->trans('Index name'),
        'read_only' => true,
        'disabled' => true,
        'required' => true
      ))
      ->add('mappingName', 'text', array(
        'label' => $this->get('translator')->trans('Mapping name'),
        'read_only' => !$add,
        'disabled' => !$add,
        'required' => true
      ))
      ->add('wipeData', 'checkbox', array(
        'label' => $this->get('translator')->trans('Wipe data?'),
        'required' => false
      ))
      ->add('mappingDefinition', 'textarea', array(
        'label' => $this->get('translator')->trans('Mapping definition'),
        'required' => true
      ))
      ->add('save', 'submit', array('label' => $this->get('translator')->trans('Save mapping')))
      ->getForm();
    $form->handleRequest($request);

    if ($form->isValid()) {
      $mapping = $form->getData();
      try {
        $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
        $indexManager->updateMapping($mapping);
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Mapping has been updated'));
        return $this->redirect($this->generateUrl('indexes'));
      } catch (Exception $ex) {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('An error as occured: ') . $ex->getMessage());
      }
    }
    $vars = array(
      'title' => $this->get('translator')->trans('Edit mapping'),
      'main_menu_item' => 'indexes',
      'form' => $form->createView(),
      'analyzers' => $analyzers,
      'fieldTypes' => $fieldTypes,
      'dateFormats' => $dateFormats,
    );
    return $this->render('ctsearch/indexes.html.twig', $vars);
  }

  /**
   * @Route("/test-service", name="test-service")
   */
  public function testServiceAction(Request $request) {
    $data = array(
      'op' => 'test',
      'domain' => 'core-techs.fr'
    );
    $r = $this->getRestData('http://localhost:8080/CtSearchWebCrawler/service', $data);
    return new \Symfony\Component\HttpFoundation\Response(json_encode($r), 200, array('Content-type' => 'text/html'));
  }

  private function getRestData($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'data=' . urlencode(json_encode($data)));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
  }

  /**
   * @Route("/indexes/ac-settings/{index_name}", name="index-ac-settings")
   */
  public function editACSettingsAction(Request $request, $index_name) {
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    $ac_settings = $indexManager->getACSettings($index_name);

    $infos = $indexManager->getElasticInfo()[$index_name];
    $mappings = isset($infos['mappings']) ? $infos['mappings'] : array();
    $field_choices = array();
    foreach ($mappings as $index => $mapping) {
      if ($mapping['name'] != '.ctsearch-autocomplete') {
        $fields = array_keys(json_decode($indexManager->getMapping($index_name, $mapping['name'])->getMappingDefinition(), true));
        foreach ($fields as $field) {
          $field_choices[$mapping['name'] . '.' . $field] = $mapping['name'] . '.' . $field;
        }
      }
    }

    $filter_choices = array();
    foreach ($indexManager->getAvailableFilters($index_name) as $filter) {
      $filter_choices[$filter] = $filter;
    }
    if (isset($filter_choices['ctsearch_ac_shingle'])) {
      unset($filter_choices['ctsearch_ac_shingle']);
    }

    $form = $this->createFormBuilder($ac_settings != null ? $ac_settings : array('index_name' => $index_name))
      ->add('index_name', 'text', array(
        'label' => $this->get('translator')->trans('Index name'),
        'read_only' => true,
        'disabled' => true,
        'required' => true,
      ))
      ->add('fields', 'choice', array(
        'label' => $this->get('translator')->trans('Fields'),
        'expanded' => true,
        'multiple' => true,
        'choices' => $field_choices,
        'attr' => array('class' => 'type-choices')
      ))
      ->add('analyzer_filters', 'choice', array(
        'label' => $this->get('translator')->trans('Analyzer filters'),
        'expanded' => true,
        'multiple' => true,
        'choices' => $filter_choices,
        'attr' => array('class' => 'type-choices')
      ))
      ->add('save', 'submit', array('label' => $this->get('translator')->trans('Save')))
      ->getForm();
    $form->handleRequest($request);

    if ($form->isValid()) {
      $indexManager->saveACSettings($form->getData());
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Settings have been saved'));
      return $this->redirect($this->generateUrl('indexes'));
    }
    $vars = array(
      'title' => $this->get('translator')->trans('Edit autocomplete settings'),
      'main_menu_item' => 'indexes',
      'form' => $form->createView(),
    );
    return $this->render('ctsearch/indexes.html.twig', $vars);
  }

  /**
   * @Route("/indexes/mapping-stat/{index_name}/{mapping_name}", name="index-mapping-stat")
   */
  public function mappingStatAction(Request $request, $index_name, $mapping_name) {
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    $mapping = $indexManager->getMapping($index_name, $mapping_name);
    $data = array(
      'docs' => 0,
      'fields' => 0
    );
    if ($mapping != null) {
      $query = array(
        'filter' => array(
          'type' => array(
            'value' => $mapping_name,
          )
        )
      );
      $res = $indexManager->search($index_name, json_encode($query));
      if (isset($res['hits']['total']) && $res['hits']['total'] > 0) {
        $data['docs'] = $res['hits']['total'];
      }
      $data['fields'] = count(json_decode($mapping->getMappingDefinition(), TRUE));
    }
    return new \Symfony\Component\HttpFoundation\Response(json_encode($data), 200, array('Content-type' => 'application/json'));
  }

}
