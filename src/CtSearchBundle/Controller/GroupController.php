<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\Classes\Group;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\Classes\Processor;
use \Symfony\Component\HttpFoundation\Response;

class GroupController extends Controller {

  /**
   * @Route("/groups", name="groups")
   */
  public function listGroupsAction(Request $request) {
    $groups = IndexManager::getInstance()->getGroups();
    return $this->render('ctsearch/group.html.twig', array(
        'title' => $this->get('translator')->trans('Groups'),
        'main_menu_item' => 'groups',
        'groups' => $groups
    ));
  }

  /**
   * @Route("/groups/add", name="group-add")
   */
  public function addGroupAction(Request $request) {
    return $this->handleAddOrEditGroup($request);
  }

  /**
   * @Route("/groups/edit", name="group-edit")
   */
  public function editGroupAction(Request $request) {
    return $this->handleAddOrEditGroup($request, $request->get('id'));
  }

  /**
   * @Route("/groups/delete", name="group-delete")
   */
  public function deleteGroupAction(Request $request) {
    if ($request->get('id') != null) {
      IndexManager::getInstance()->deleteGroup($request->get('id'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Group has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('groups'));
  }


  private function handleAddOrEditGroup($request, $id = null) {
    if ($id == null) { //Add
      $group = new Group('', '', [], [], [], []);
    } else { //Edit
      $group = IndexManager::getInstance()->getGroup($request->get('id'));
    }
    $info = IndexManager::getInstance()->getElasticInfo();
    $indexChoices = [];
    foreach(array_keys($info) as $index){
      $indexChoices[$index] = $index;
    }
    $datasources = IndexManager::getInstance()->getDatasources(null);
    $datasourceChoices = [];
    foreach($datasources as $ds){
      $datasourceChoices[$ds->getName()] = $ds->getId();
    }
    $matchingLists = IndexManager::getInstance()->getMatchingLists();
    $matchingListsChoices = [];
    foreach($matchingLists as $item){
      $matchingListsChoices[$item->getName()] = $item->getId();
    }
    $dictionaries = IndexManager::getInstance()->getSynonymsDictionaries();
    $dictionariesChoices = [];
    foreach($dictionaries as $item){
      $dictionariesChoices[$item['name']] = $item['name'];
    }
    $form = $this->createFormBuilder($group)
      ->add('id', TextType::class, array(
        'label' => $this->get('translator')->trans('ID'),
        'required' => true,
        'disabled' => $id != null
      ))
      ->add('name', TextType::class, array(
        'label' => $this->get('translator')->trans('Groupe name'),
        'required' => true,
      ))
      ->add('indexes', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Allowed indexes'),
        'choices' => $indexChoices,
        'required' => true,
        'expanded' => true,
        'multiple' => true
      ))
      ->add('datasources', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Allowed datasources'),
        'choices' => $datasourceChoices,
        'required' => true,
        'expanded' => true,
        'multiple' => true
      ))
      ->add('matchingLists', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Allowed matching lists'),
        'choices' => $matchingListsChoices,
        'required' => true,
        'expanded' => true,
        'multiple' => true
      ))
      ->add('dictionaries', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Allowed dictionaries'),
        'choices' => $dictionariesChoices,
        'required' => true,
        'expanded' => true,
        'multiple' => true
      ))
      ->add('save', SubmitType::class, array('label' => $this->get('translator')->trans('Save')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      IndexManager::getInstance()->saveGroup($form->getData());
      if ($id == null) {
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('New group has been added successfully'));
      } else {
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Group has been updated successfully'));
      }
      return $this->redirect($this->generateUrl('groups'));
    }
    return $this->render('ctsearch/group.html.twig', array(
        'title' => $id == null ? $this->get('translator')->trans('New group') : $this->get('translator')->trans('Edit group'),
        'main_menu_item' => 'groups',
        'form' => $form->createView()
    ));
  }


}
