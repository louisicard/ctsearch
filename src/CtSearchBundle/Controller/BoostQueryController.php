<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\Classes\BoostQuery;
use CtSearchBundle\CtSearchBundle;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Component\Routing\Annotation\Route;

class BoostQueryController extends Controller {

  /**
   * @Route("/boost-queries", name="boost-queries")
   */
  public function listGroupsAction(Request $request) {
    $boostQueries = IndexManager::getInstance()->getBoostQueries();
    return $this->render('ctsearch/boost-query.html.twig', array(
        'title' => $this->get('translator')->trans('Boost queries'),
        'main_menu_item' => 'boost-queries',
        'boostQueries' => $boostQueries
    ));
  }

  /**
   * @Route("/boost-queries/add", name="boost-query-add")
   */
  public function addBoostQueryAction(Request $request) {
    return $this->handleAddOrEditBoostQuery($request);
  }

  /**
   * @Route("/boost-queries/edit", name="boost-query-edit")
   */
  public function editBoostQueryAction(Request $request) {
    return $this->handleAddOrEditBoostQuery($request, $request->get('id'));
  }

  /**
   * @Route("/boost-queries/delete", name="boost-query-delete")
   */
  public function deleteBoostQueryAction(Request $request) {
    if ($request->get('id') != null) {
      IndexManager::getInstance()->deleteBoostQuery($request->get('id'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Boost query has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('boost-queries'));
  }


  private function handleAddOrEditBoostQuery($request, $id = null) {
    if ($id == null) { //Add
      $boostQuery = new BoostQuery('', '', '');
    } else { //Edit
      $boostQuery = IndexManager::getInstance()->getBoostQuery($request->get('id'));
    }
    $info = IndexManager::getInstance()->getElasticInfo();
    $mappingChoices = array(
      'Select >' => ''
    );
    foreach($info as $index => $data){
      foreach($data['mappings'] as $mapping) {
        $mappingChoices[$index . '.' . $mapping['name']] = $index . '.' . $mapping['name'];
      }
    }
    $form = $this->createFormBuilder($boostQuery)
      ->add('target', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Target'),
        'required' => true,
        'choices' => $mappingChoices
      ))
      ->add('definition', TextareaType::class, array(
        'label' => $this->get('translator')->trans('Definition'),
        'required' => true,
      ))
      ->add('save', SubmitType::class, array('label' => $this->get('translator')->trans('Save')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      $json = json_decode($form->getData()->getDefinition());
      if($json == null){
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Definition must be valid JSON'));
      }
      else {
        /** @var BoostQuery $boostQuery */
        $boostQuery = $form->getData();

        $indexName = substr($boostQuery->getTarget(), 0, strpos($boostQuery->getTarget(), '.', 1));
        $mappingName = substr($boostQuery->getTarget(), strpos($boostQuery->getTarget(), '.', 1) + 1);

        $testQuery = array(
          'query' => json_decode($boostQuery->getDefinition(), true)
        );
        try{
          IndexManager::getInstance()->search($indexName, json_encode($testQuery), 0, 0, $mappingName);
          $testOk = true;
        }
        catch(\Exception $ex){
          $testOk = false;
        }

        if(!$testOk){
          CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Server refused your query : ' . $ex->getMessage()));
        }
        else {
          $boostQuery->setDefinition(json_encode($json, JSON_PRETTY_PRINT));
          IndexManager::getInstance()->saveBoostQuery($form->getData(), $id);
          if ($id == null) {
            CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('New boost query has been added successfully'));
          } else {
            CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Boost query has been updated successfully'));
          }
          return $this->redirect($this->generateUrl('boost-queries'));
        }
      }
    }
    return $this->render('ctsearch/boost-query.html.twig', array(
        'title' => $id == null ? $this->get('translator')->trans('New boost query') : $this->get('translator')->trans('Edit boost query'),
        'main_menu_item' => 'boost-queries',
        'form' => $form->createView()
    ));
  }


}
