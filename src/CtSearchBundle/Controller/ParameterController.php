<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\Classes\BoostQuery;
use CtSearchBundle\Classes\Parameter;
use CtSearchBundle\CtSearchBundle;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Component\Routing\Annotation\Route;

class ParameterController extends Controller {

  /**
   * @Route("/parameters", name="parameters")
   */
  public function listParameterssAction(Request $request) {
    $parameters = IndexManager::getInstance()->getParameters();
    return $this->render('ctsearch/parameters.html.twig', array(
        'title' => $this->get('translator')->trans('Parameters'),
        'main_menu_item' => 'parameters',
        'parameters' => $parameters
    ));
  }

  /**
   * @Route("/parameters/add", name="parameter-add")
   */
  public function addParameterAction(Request $request) {
    return $this->handleAddOrEditParameter($request);
  }

  /**
   * @Route("/parameters/edit", name="parameter-edit")
   */
  public function editParameterAction(Request $request) {
    return $this->handleAddOrEditParameter($request, $request->get('name'));
  }

  /**
   * @Route("/parameters/delete", name="parameter-delete")
   */
  public function deleteParameterAction(Request $request) {
    if ($request->get('name') != null) {
      IndexManager::getInstance()->deleteParameter($request->get('name'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Parameter has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No name provided'));
    }
    return $this->redirect($this->generateUrl('parameters'));
  }


  private function handleAddOrEditParameter($request, $name = null)
  {
    if ($name == null) { //Add
      $parameter = new Parameter('', '');
    } else { //Edit
      $parameter = IndexManager::getInstance()->getParameter($request->get('name'));
    }
    $info = IndexManager::getInstance()->getElasticInfo();
    $mappingChoices = array(
      'Select >' => ''
    );
    foreach ($info as $index => $data) {
      foreach ($data['mappings'] as $mapping) {
        $mappingChoices[$index . '.' . $mapping['name']] = $index . '.' . $mapping['name'];
      }
    }
    $form = $this->createFormBuilder($parameter)
      ->add('name', TextType::class, array(
        'label' => $this->get('translator')->trans('Name'),
        'required' => true,
        'disabled' => $name != null
      ))
      ->add('value', TextType::class, array(
        'label' => $this->get('translator')->trans('Value'),
        'required' => true,
      ))
      ->add('save', SubmitType::class, array('label' => $this->get('translator')->trans('Save')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {

      IndexManager::getInstance()->saveParameter($form->getData());
      if ($name == null) {
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('New parameter has been added successfully'));
      } else {
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Parameter has been updated successfully'));
      }
      return $this->redirect($this->generateUrl('parameters'));
    }

    return $this->render('ctsearch/parameters.html.twig', array(
      'title' => $name == null ? $this->get('translator')->trans('New parameter') : $this->get('translator')->trans('Edit parameter'),
      'main_menu_item' => 'parameters',
      'form' => $form->createView()
    ));
  }


}
