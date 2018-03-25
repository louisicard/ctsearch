<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\CtSearchBundle;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Component\HttpFoundation\Response;

class EditionController extends Controller {

  /**
   * @Route("/get-edit-record-form", name="get_edit_record_form")
   */
  public function getEditRecordFormAction(Request $request)
  {
    $mapping = $request->get('mapping');
    $id = $request->get('id');

    $index = strpos($mapping, '.') !== 0 ? explode('.', $mapping)[0] : '.' . explode('.', $mapping)[1];
    $mappingName = strpos($mapping, '.') !== 0 ? explode('.', $mapping)[1] : explode('.', $mapping)[2];

    $mappingDef = json_decode(IndexManager::getInstance()->getMapping($index, $mappingName)->getMappingDefinition(), true);

    $res = IndexManager::getInstance()->search($index, '{"query":{"ids":{"values":["' . $id . '"]}}}');
    if(isset($res['hits']['hits'][0])) {
      $record = $res['hits']['hits'][0];
    }
    else {
      $record = NULL;
    }

    return new Response(json_encode(array('mapping' => $mappingDef, 'record' => $record), JSON_PRETTY_PRINT), 200, array('Content-type' => 'application/json; charset=utf-8'));
  }

  /**
   * @Route("/edit-record", name="edit_record")
   */
  public function editRecordAction(Request $request)
  {
    $mapping = $request->get('mapping');
    $id = $request->get('id');
    $doc = json_decode($request->getContent(), TRUE);
    $doc['_id'] = $id;

    $index = strpos($mapping, '.') !== 0 ? explode('.', $mapping)[0] : '.' . explode('.', $mapping)[1];
    $mappingName = strpos($mapping, '.') !== 0 ? explode('.', $mapping)[1] : explode('.', $mapping)[2];

    IndexManager::getInstance()->indexDocument($index, $mappingName, $doc);

    return new Response(json_encode(array('status' => 'ok'), JSON_PRETTY_PRINT), 200, array('Content-type' => 'application/json; charset=utf-8'));
  }

}
