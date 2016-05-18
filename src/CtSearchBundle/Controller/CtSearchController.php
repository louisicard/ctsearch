<?php
/**
 * Created by PhpStorm.
 * User: Louis Sicard
 * Date: 18/05/2016
 * Time: 11:37
 */

namespace CtSearchBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CtSearchController extends Controller
{

  public function createFormBuilder($data = null, array $options = array())
  {
    return parent::createFormBuilder($data, $options);
  }

  public function get($id)
  {
    return parent::get($id);
  }

}