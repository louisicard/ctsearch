<?php

namespace CtSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use CtSearchBundle\Classes\Index;
use CtSearchBundle\Classes\Mapping;
use \Exception;

class MatchingListController extends Controller {

  /**
   * @Route("/matching-list", name="matching-lists")
   */
  public function listMatchingListsAction(Request $request) {
    return $this->render('ctsearch/matching-lists.html.twig', array(
          'title' => $this->get('translator')->trans('Matching lists'),
          'main_menu_item' => 'matching-lists',
          'matching_lists' => IndexManager::getInstance()->getMatchingLists(),
    ));
  }

  /**
   * @Route("/matching-list/add", name="matching-list-add")
   */
  public function addMatchingListAction(Request $request) {
    return $this->handleAddOrEditMatchingList($request);
  }

  /**
   * @Route("/matching-list/edit", name="matching-list-edit")
   */
  public function editMatchingListAction(Request $request) {
    return $this->handleAddOrEditMatchingList($request, $request->get('id'));
  }

  private function handleAddOrEditMatchingList($request, $id = null) {
    if ($id == null) { //Add
      $matchingList = new \CtSearchBundle\Classes\MatchingList('');
    } else { //Edit
      $matchingList = IndexManager::getInstance()->getMatchingList($request->get('id'));
      $list = $matchingList->getList();
      //ksort($list, SORT_NATURAL | SORT_FLAG_CASE);
      if (empty($list))
        $matchingList->setList('{}');
      else
        $matchingList->setList(json_encode($list, JSON_PRETTY_PRINT));
    }
    $form = $this->createFormBuilder($matchingList)
        ->add('id', HiddenType::class)
        ->add('name', TextType::class, array(
          'label' => $this->get('translator')->trans('Matching list name'),
          'required' => true,
        ))
        ->add('list', TextareaType::class, array(
          'label' => $this->get('translator')->trans('JSON Definition'),
          'required' => true
        ))
        ->add('save', SubmitType::class, array('label' => $this->get('translator')->trans('Save')))
        ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      if (!is_array(json_decode($matchingList->getList())) && json_decode($matchingList->getList()) == null) {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('JSON parsing failed.'));
      } else {
        $matchingList = $form->getData();
        $def = json_decode($matchingList->getList());
        if (empty($def))
          $matchingList->setList('{}');
        IndexManager::getInstance()->saveMatchingList($matchingList);
        if ($id == null) {
          CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('New matching list has been added successfully'));
        } else {
          CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Matching list has been updated successfully'));
        }
        if ($id == null)
          return $this->redirect($this->generateUrl('matching-lists'));
        else {
          return $this->redirect($this->generateUrl('matching-list-edit', array('id' => $id)));
        }
      }
    }
    $vars = array(
      'title' => $id == null ? $this->get('translator')->trans('New matching list') : $this->get('translator')->trans('Edit matching list'),
      'main_menu_item' => 'matching-lists',
      'form' => $form->createView()
    );
    if ($id != null) {

      $infos = IndexManager::getInstance()->getElasticInfo();
      $select = '<select id="matching-list-field-selector"><option value="">Select a field</option>';
      foreach ($infos as $index => $info) {
        if(isset($info['mappings'])) {
          $select .= '<optgroup label="' . htmlentities($index) . '">';
          foreach ($info['mappings'] as $mapping) {
            $mapping = IndexManager::getInstance()->getMapping($index, $mapping['name']);
            foreach (json_decode($mapping->getMappingDefinition(), true) as $field => $info_field) {
              $select .= '<option value="' . $index . '.' . $mapping->getMappingName() . '.' . $field . '">' . $index . '.' . $mapping->getMappingName() . '.' . $field . '</option>';
            }
          }
          $select .= '</optgroup>';
        }
      }
      $select .= '</select>';
      $select_size = '<select id="matching-list-size-selector"><option value="">Select max number of values to import</option>';
      $select_size .= '<option value="20">20</option>';
      $select_size .= '<option value="50">50</option>';
      $select_size .= '<option value="100">100</option>';
      $select_size .= '<option value="200">200</option>';
      $select_size .= '<option value="300">300</option>';
      $select_size .= '</select>';
      $vars['init_from_index_action'] = $select . $select_size . '<a href="' . $this->generateUrl('matching-init-from-index', array('id' => $id)) . '">' . $this->get('translator')->trans('Initialize from index') . '</a>';
      $vars['import_file_link'] = $this->generateUrl('matching-import-file', array('id' => $id));
      $vars['export_link'] = $this->generateUrl('matching-export', array('id' => $id));
    }
    return $this->render('ctsearch/matching-lists.html.twig', $vars);
  }

  /**
   * @Route("/matching-lists/delete", name="matching-list-delete")
   */
  public function deleteMatchingListAction(Request $request) {
    if ($request->get('id') != null) {
      IndexManager::getInstance()->deleteMatchingList($request->get('id'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Matching list has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('matching-lists'));
  }

  /**
   * @Route("/matching-list/import-file", name="matching-import-file")
   */
  public function importMatchingListFileAction(Request $request) {
    if ($request->get('id') != null) {
      $matchingList = IndexManager::getInstance()->getMatchingList($request->get('id'));
      $form = $this->createFormBuilder()
          ->add('matching_list_id', HiddenType::class, array(
            'data' => $matchingList->getId()
          ))
          ->add('import_file', FileType::class, array(
            'label' => 'File to import (CSV comma separated with no headers)',
            'required' => true
          ))
          ->add('ok', SubmitType::class, array(
            'label' => 'Import',
          ))
          ->getForm();
      $form->handleRequest($request);
      if ($form->isValid()) {
        $data = $form->getData();
        $file = $data['import_file'];
        $extension = pathinfo($file->getClientOriginalName())['extension'];
        if (strtolower($extension) != 'csv') {
          CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Only CSV files can be imported'));
        } else {
          $fp = fopen($file->getRealPath(), 'r');
          $list = array();
          while(($line = fgetcsv($fp))) {
            if (count($line) >= 2) {
              $list[$line[0]] = $line[1];
            }
          }
          fclose($fp);
          unlink($file->getRealPath());
          $matchingList->setList(json_encode($list));
          IndexManager::getInstance()->saveMatchingList($matchingList);
          CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans(count($list) . ' values imported'));
          return $this->redirect($this->generateUrl('matching-lists'));
        }
      }
      return $this->render('ctsearch/matching-lists.html.twig', array(
            'title' => $this->get('translator')->trans('Import file'),
            'main_menu_item' => 'matching-lists',
            'form' => $form->createView(),
            'matchingList' => $matchingList
      ));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
      return $this->redirect($this->generateUrl('matching-lists'));
    }
  }

  /**
   * @Route("/matching-list/export", name="matching-export")
   */
  public function exportMatchingListFileAction(Request $request) {
    if ($request->get('id') != null) {
      $matchingList = IndexManager::getInstance()->getMatchingList($request->get('id'));
      $list = json_decode(json_encode($matchingList->getList()), true);
      $data = '';
      foreach ($list as $k => $v) {
        $data .= '"' . $k . '","' . $v . "\"\r\n";
      }
      $response = new \Symfony\Component\HttpFoundation\Response($data, 200, array(
        'Content-type' => 'text/csv; encoding=utf-8',
        'Content-disposition' => 'attachment; filename=export.csv',
      ));
      return $response;
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
      return $this->redirect($this->generateUrl('matching-lists'));
    }
  }

  /**
   * @Route("/matching-list/init-from-index", name="matching-init-from-index")
   */
  public function initMatchingListFromIndexAction(Request $request) {
    if ($request->get('id') != null && $request->get('field') != null && $request->get('size') != null) {
      $matchingList = IndexManager::getInstance()->getMatchingList($request->get('id'));
      $field_data = explode('.', $request->get('field'));
      $indexName = $field_data[0];
      $mappingName = $field_data[1];
      $field = $field_data[2];
      $result = IndexManager::getInstance()->search($indexName, json_encode(array(
        'query' => array(
          'filtered' => array(
            'filter' => array(
              'type' => array(
                'value' => $mappingName
              )
            )
          )
        ),
        'aggs' => array(
          'values' => array(
            'terms' => array(
              'field' => $field,
              'order' => array(
                '_count' => 'desc'
              ),
              'size' => $request->get('size')
            )
          )
        )
      )));
      $list = array();
      if (isset($result['aggregations']['values']['buckets'])) {
        foreach ($result['aggregations']['values']['buckets'] as $bucket) {
          $list[$bucket['key']] = '';
        }
      }
      $matchingList->setList(json_encode($list));
      IndexManager::getInstance()->saveMatchingList($matchingList);
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans(count($list) . ' values imported'));
      return $this->redirect($this->generateUrl('matching-lists'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id or field or size provided'));
      return $this->redirect($this->generateUrl('matching-lists'));
    }
  }

}
