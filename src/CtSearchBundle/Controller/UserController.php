<?php

namespace CtSearchBundle\Controller;

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
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\Classes\Processor;
use \Symfony\Component\HttpFoundation\Response;

class UserController extends Controller {

  /**
   * @Route("/users", name="users")
   */
  public function listGroupsAction(Request $request) {
    $users = IndexManager::getInstance()->getUsers();
    return $this->render('ctsearch/user.html.twig', array(
        'title' => $this->get('translator')->trans('Users'),
        'main_menu_item' => 'users',
        'users' => $users
    ));
  }

  /**
   * @Route("/users/add", name="user-add")
   */
  public function addSearchPageAction(Request $request) {
    return $this->handleAddOrEditUser($request);
  }

  /**
   * @Route("/users/edit", name="user-edit")
   */
  public function editSearchPageAction(Request $request) {
    return $this->handleAddOrEditUser($request, $request->get('uid'));
  }

  /**
   * @Route("/users/delete", name="user-delete")
   */
  public function deleteSearchPageAction(Request $request) {
    if ($request->get('uid') != null) {
      IndexManager::getInstance()->deleteUser($request->get('uid'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('User has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('users'));
  }


  private function handleAddOrEditUser($request, $uid = null) {
    if ($uid == null) { //Add
      $user = new User('', [], '', '', []);
    } else { //Edit
      $user = IndexManager::getInstance()->getUser($request->get('uid'));
    }
    $groups = IndexManager::getInstance()->getGroups();
    $groupsChoices = [];
    foreach($groups as $group){
      $groupsChoices[$group->getName()] = $group->getId();
    }
    $roles = $this->container->getParameter('security.role_hierarchy.roles');
    $rolesChoices = [];
    foreach($roles as $k => $kk){
      if(!in_array($k, array_keys($rolesChoices))){
        $rolesChoices[$k] = $k;
      }
      foreach($kk as $kkk){
        if(!in_array($kkk, array_keys($rolesChoices))){
          $rolesChoices[$kkk] = $kkk;
        }
      }
    }
    ksort($rolesChoices);
    $form = $this->createFormBuilder($user)
      ->add('uid', TextType::class, array(
        'label' => $this->get('translator')->trans('Username'),
        'required' => true,
        'disabled' => $uid != null
      ))
      ->add('email', TextType::class, array(
        'label' => $this->get('translator')->trans('Email'),
        'required' => true,
      ))
      ->add('fullName', TextType::class, array(
        'label' => $this->get('translator')->trans('Full name'),
        'required' => true,
      ))
      ->add('newPassword', PasswordType::class, array(
        'label' => $this->get('translator')->trans('Password'),
        'mapped' => false,
        'required' => false,
      ))
      ->add('groups', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Groups'),
        'choices' => $groupsChoices,
        'required' => true,
        'expanded' => true,
        'multiple' => true
      ))
      ->add('roles', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Roles'),
        'choices' => $rolesChoices,
        'required' => true,
        'expanded' => true,
        'multiple' => true
      ))
      ->add('save', SubmitType::class, array('label' => $this->get('translator')->trans('Save')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      /** @var User $user */
      $user = $form->getData();
      $user->setRoles(array_values($user->getRoles()));
      $plain = $form->get('newPassword')->getData();
      if(!empty($plain)){
        $encoder = $this->container->get('security.password_encoder');
        $encoded = $encoder->encodePassword($user, $plain);
        $user->setPassword($encoded);
      }
      IndexManager::getInstance()->saveUser($user);
      if ($uid == null) {
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('New user has been added successfully'));
      } else {
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('User has been updated successfully'));
      }
      return $this->redirect($this->generateUrl('users'));
    }
    return $this->render('ctsearch/group.html.twig', array(
        'title' => $uid == null ? $this->get('translator')->trans('New user') : $this->get('translator')->trans('Edit user'),
        'main_menu_item' => 'users',
        'form' => $form->createView()
    ));
  }


}
