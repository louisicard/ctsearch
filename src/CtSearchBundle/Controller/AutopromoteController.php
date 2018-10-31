<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\Classes\Autopromote;
use CtSearchBundle\Classes\Group;
use CtSearchBundle\Classes\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\Classes\Processor;
use \Symfony\Component\HttpFoundation\Response;

class AutopromoteController extends Controller {

  /**
   * @Route("/autopromotes", name="autopromotes")
   */
  public function listAutopromotesAction(Request $request) {
    $autopromotes = IndexManager::getInstance()->getAutopromotes();
    return $this->render('ctsearch/autopromote.html.twig', array(
        'title' => $this->get('translator')->trans('Auto-promotes'),
        'main_menu_item' => 'autopromotes',
        'autopromotes' => $autopromotes
    ));
  }

  /**
   * @Route("/autopromotes/add", name="autopromote-add")
   */
  public function addAutopromoteAction(Request $request) {
    return $this->handleAddOrEditAutopromote($request);
  }

  /**
   * @Route("/autopromotes/edit", name="autopromote-edit")
   */
  public function editAutopromoteAction(Request $request) {
    return $this->handleAddOrEditAutopromote($request, $request->get('id'));
  }

  /**
   * @Route("/autopromotes/delete", name="autopromote-delete")
   */
  public function deleteAutopromoteAction(Request $request) {
    if ($request->get('id') != null) {
      $autopromote = IndexManager::getInstance()->getAutopromote($request->get('id'));
      if($autopromote != null) {
        IndexManager::getInstance()->deleteAutopromote($autopromote);
      }
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Autopromote has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('autopromotes'));
  }


  private function handleAddOrEditAutopromote($request, $id = null) {
    if ($id == null) { //Add
      $autopromote = new Autopromote('', '', '', '', '', '', '', '');
    } else { //Edit
      $autopromote = IndexManager::getInstance()->getAutopromote($id);
    }

    $indexes = array_keys(IndexManager::getInstance()->getElasticInfo($this));
    asort($indexes);
    $indexChoices = array(
      'Select >' => '',
    );
    $analyzerChoices = array(
      'Please select an index first' => '',
    );
    foreach($indexes as $index){
      $indexChoices[$index] = $index;
    }
    $formBuilder = $this->createFormBuilder($autopromote, array('csrf_protection' => false))
      ->add('title', TextType::class, array(
        'label' => $this->get('translator')->trans('Title'),
        'required' => true,
      ))
      ->add('url', TextType::class, array(
        'label' => $this->get('translator')->trans('URL'),
        'required' => true,
      ))
      ->add('image', TextType::class, array(
        'label' => $this->get('translator')->trans('Image'),
        'required' => false,
      ))
      ->add('body', TextareaType::class, array(
        'label' => $this->get('translator')->trans('Body (HTML tags arre allowed)'),
        'required' => true,
      ))
      ->add('keywords', TextareaType::class, array(
        'label' => $this->get('translator')->trans('Keywords'),
        'required' => true,
      ))
      ->add('index', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Index'),
        'required' => true,
        'choices' => $indexChoices,
        'disabled' => $id != null
      ));
    if($id == null) {
      $formBuilder
        ->add('analyzer', ChoiceType::class, array(
          'label' => $this->get('translator')->trans('Analyzer'),
          'required' => true,
          'choices' => $analyzerChoices
        ))
        ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
          $obj = $event->getData();
          $analyzerChoices = array(
            'Select >' => '',
          );
          $analyzers = $this->getAnalyzersForIndex($obj['index']);
          foreach ($analyzers as $a) {
            $analyzerChoices[$a] = $a;
          }
          $event->getForm()->add('analyzer', ChoiceType::class, array(
            'label' => $this->get('translator')->trans('Analyzer'),
            'required' => true,
            'choices' => $analyzerChoices
          ));
        });
    }
    $formBuilder
      ->add('save', SubmitType::class, array('label' => $this->get('translator')->trans('Save')));
    $form = $formBuilder->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {

      IndexManager::getInstance()->saveAutopromote($form->getData());

      if ($id == null) {
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('New auto-promote has been added successfully'));
      } else {
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Auto-promote has been updated successfully'));
      }
      return $this->redirect($this->generateUrl('autopromotes'));
    }
    return $this->render('ctsearch/autopromote.html.twig', array(
        'title' => $id == null ? $this->get('translator')->trans('New auto-promote') : $this->get('translator')->trans('Edit auto-promote'),
        'main_menu_item' => 'autopromotes',
        'form' => $form->createView()
    ));
  }

  /**
   * @Route("/autopromotes/ajax/get-analyzers", name="autopromote-ajax-get-analyzers")
   */
  public function getAnalyzersAction(Request $request) {
    $analyzers = $this->getAnalyzersForIndex($request->get('index'));
    $exists = IndexManager::getInstance()->mappingExists($request->get('index'), 'ctsearch_autopromote');
    $r = array(
      'enabled' => !$exists,
      'value' => $exists ? IndexManager::getInstance()->getAutopromoteAnalyzer($request->get('index')) : NULL,
      'analyzers' => $analyzers
    );
    return new Response(json_encode($r), 200, array('Content-Type' => 'application/json; charset=utf-8'));
  }

  private function getAnalyzersForIndex($index){
    return IndexManager::getInstance()->getAnalyzers($index);
  }

}
