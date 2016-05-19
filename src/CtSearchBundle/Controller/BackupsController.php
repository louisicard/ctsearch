<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\Classes\StatCompiler;
use CtSearchBundle\CtSearchBundle;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\ServerErrorResponseException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Component\HttpFoundation\Response;

class BackupsController extends Controller
{

  /**
   * @Route("/backups", name="backups")
   */
  public function listBackupsAction(Request $request)
  {

    $repos = IndexManager::getInstance()->getBackupRepositories();

    $snapshots = array();

    foreach (array_keys($repos) as $repo) {
      $s = IndexManager::getInstance()->getSnapshots($repo);
      if (isset($s['snapshots']) && count($s['snapshots']) > 0) {
        foreach ($s['snapshots'] as $i => $snap) {
          if (isset($snap['end_time_in_millis'])) {
            $s['snapshots'][$i]['end_time_clean'] = date('Y-m-d H:i:s', round($snap['end_time_in_millis'] / 1000));
          }
        }
        $snapshots[$repo] = $s['snapshots'];
      }
    }

    $params = array(
      'title' => $this->get('translator')->trans('Backups'),
      'main_menu_item' => 'backups',
      'repos' => $repos,
      'snapshots' => $snapshots
    );
    return $this->render('ctsearch/backups.html.twig', $params);
  }

  /**
   * @Route("/backups/create-repo", name="backups_create_repo")
   * @Route("/backups/edit-repo/{name}", name="backups_edit_repo")
   */
  public function createOrEditRepoAction(Request $request, $name = null)
  {

    if ($name != null) {
      $repo = IndexManager::getInstance()->getRepository($name);
      $repo_name = array_keys($repo)[0];
      $data = array(
        'name' => $repo_name,
        'type' => $repo[$repo_name]['type'],
        'compress' => $repo[$repo_name]['settings']['compress'] == 'true',
        'location' => $repo[$repo_name]['settings']['location'],
      );
    } else {
      $data = null;
    }

    $form = $this->createFormBuilder($data)
      ->add('name', TextType::class, array(
        'label' => $this->get('translator')->trans('Name'),
        'required' => true,
        'disabled' => $name != null
      ))
      ->add('type', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Type'),
        'required' => true,
        'choices' => array(
          $this->get('translator')->trans('Select') => '',
          $this->get('translator')->trans('File system') => 'fs'
        )
      ))
      ->add('location', TextType::class, array(
        'label' => $this->get('translator')->trans('Location (must be declared in Elastic conf [repo.path])'),
        'required' => true,
      ))
      ->add('compress', CheckboxType::class, array(
        'label' => $this->get('translator')->trans('Compressed'),
        'required' => false,
      ))
      ->add('submit', SubmitType::class, array(
        'label' => $this->get('translator')->trans('Submit')
      ))
      ->getForm();

    $form->handleRequest($request);

    if ($form->isValid()) {
      try {
        IndexManager::getInstance()->createRepository($form->getData());
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Repository has been ' . ($name == null ? 'created' : 'updated')));
        return $this->redirect($this->generateUrl('backups'));
      } catch (ServerErrorResponseException $ex) {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Repository could not be created please check your settings'));
      }
    }

    $params = array(
      'title' => $this->get('translator')->trans(($name == null ? 'Create' : 'Edit') . ' a repository'),
      'main_menu_item' => 'backups',
      'form' => $form->createView()
    );
    return $this->render('ctsearch/backups-form.html.twig', $params);
  }

  /**
   * @Route("/backups/delete-repo/{name}", name="backups_delete_repo")
   */
  public function deleteRepoAction(Request $request, $name)
  {
    try {
      IndexManager::getInstance()->deleteRepository($name);
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Repository has deleted'));

    } catch (ServerErrorResponseException $ex) {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Repository could not be deleted please check your settings'));
    }
    return $this->redirect($this->generateUrl('backups'));
  }

  /**
   * @Route("/backups/delete-snapshot/{repoName}/{name}", name="backups_delete_snapshot")
   */
  public function deleteSnapshot(Request $request, $repoName, $name)
  {
    try {
      IndexManager::getInstance()->deleteSnapshot($repoName, $name);
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Snapshot has deleted'));

    } catch (ServerErrorResponseException $ex) {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Snapshot could not be deleted please check your settings'));
    }
    return $this->redirect($this->generateUrl('backups'));
  }

  /**
   * @Route("/backups/snapshot/create", name="backups_create_snapshot")
   */
  public function createSnapshotAction(Request $request)
  {
    $repos = IndexManager::getInstance()->getBackupRepositories();
    $repoChoices = array(
      $this->get('translator')->trans('Select') => ''
    );
    foreach (array_keys($repos) as $repo) {
      $repoChoices[$repo] = $repo;
    }
    $info = IndexManager::getInstance()->getElasticInfo();
    $indexChoices = array();
    foreach ($info as $k => $data) {
      $indexChoices[$k] = $k;
    }
    $form = $this->createFormBuilder(null)
      ->add('name', TextType::class, array(
        'label' => $this->get('translator')->trans('Snapshot name'),
        'required' => true
      ))
      ->add('repo', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Repository'),
        'choices' => $repoChoices,
        'required' => true
      ))
      ->add('indexes', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Indexes to backup'),
        'choices' => $indexChoices,
        'required' => true,
        'expanded' => true,
        'multiple' => true
      ))
      ->add('ignoreUnavailable', CheckboxType::class, array(
        'label' => $this->get('translator')->trans('Ignore unavailable'),
        'required' => false,
      ))
      ->add('includeGlobalState', CheckboxType::class, array(
        'label' => $this->get('translator')->trans('Iclude global state'),
        'required' => false,
      ))
      ->add('submit', SubmitType::class, array(
        'label' => $this->get('translator')->trans('Submit')
      ))
      ->getForm();

    $form->handleRequest($request);

    if ($form->isValid()) {
      try {
        $data = $form->getData();
        IndexManager::getInstance()->createSnapshot($data['repo'], $data['name'], $data['indexes'], $data['ignoreUnavailable'], $data['includeGlobalState']);
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Snapshot has been created'));
        return $this->redirect($this->generateUrl('backups'));
      } catch (ServerErrorResponseException $ex) {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Snapshot could not be created please check your settings'));
      } catch (BadRequest400Exception $ex2) {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Snapshot could not be created : ' . $ex2->getMessage()));
      }
    }

    $params = array(
      'title' => $this->get('translator')->trans('Create a snapshot'),
      'main_menu_item' => 'backups',
      'form' => $form->createView()
    );
    return $this->render('ctsearch/backups-form.html.twig', $params);
  }

  /**
   * @Route("/backups/snapshot/restore/{repoName}/{name}", name="backups_restore_snapshot")
   */
  public function restoreSnapshotAction(Request $request, $repoName, $name)
  {

    $snapshot = IndexManager::getInstance()->getSnapshot($repoName, $name);

    $indexesChoices = array();
    foreach ($snapshot['indices'] as $index) {
      $indexesChoices[$index] = $index;
    }

    $form = $this->createFormBuilder(null)
      ->add('indexes', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Indexes to restore (if none selected, all will be restored)'),
        'required' => true,
        'expanded' => true,
        'multiple' => true,
        'choices' => $indexesChoices
      ))
      ->add('renamePattern', TextType::class, array(
        'label' => $this->get('translator')->trans('Rename pattern (cf https://www.elastic.co/guide/en/elasticsearch/reference/current/modules-snapshots.html)'),
        'required' => false,
        'data' => '(.+)'
      ))
      ->add('renameReplacement', TextType::class, array(
        'label' => $this->get('translator')->trans('Rename replacement (cf https://www.elastic.co/guide/en/elasticsearch/reference/current/modules-snapshots.html)'),
        'required' => false,
        'data' => 'restored_$1'
      ))
      ->add('ignoreUnavailable', CheckboxType::class, array(
        'label' => $this->get('translator')->trans('Ignore unavailable'),
        'required' => false,
      ))
      ->add('includeGlobalState', CheckboxType::class, array(
        'label' => $this->get('translator')->trans('Iclude global state'),
        'required' => false,
      ))
      ->add('submit', SubmitType::class, array(
        'label' => $this->get('translator')->trans('Submit')
      ))
      ->getForm();

    $form->handleRequest($request);

    if ($form->isValid()) {
      $data = $form->getData();
      try {
        IndexManager::getInstance()->restoreSnapshot($repoName, $name, $data);
        CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Snapshot has been restored'));
      } catch (ServerErrorResponseException $ex) {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Snapshot could not be restored : ' . $ex->getMessage()));
      } catch (BadRequest400Exception $ex2) {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('Snapshot could not be restored : ' . $ex2->getMessage()));
      }
    }

    $params = array(
      'title' => $this->get('translator')->trans('Restore a snapshot'),
      'main_menu_item' => 'backups',
      'form' => $form->createView()
    );
    return $this->render('ctsearch/backups-form.html.twig', $params);
  }

}
