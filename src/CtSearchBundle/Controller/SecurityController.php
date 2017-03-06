<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 06/03/2017
 * Time: 18:40
 */

namespace CtSearchBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends Controller
{

  /**
   * @Route("/login", name="login")
   */
  public function loginAction(Request $request)
  {
    /** @var AuthenticationUtils $authenticationUtils */
    $authenticationUtils = $this->get('security.authentication_utils');

    // get the login error if there is one
    $error = $authenticationUtils->getLastAuthenticationError();

    // last username entered by the user
    $lastUsername = $authenticationUtils->getLastUsername();

    return $this->render('ctsearch/login.html.twig', array(
      'title' => $this->get('translator')->trans('Login'),
      'main_menu_item' => 'login',
      'no_menu' => true,
      'last_username' => $lastUsername,
      'error'         => $error,
    ));
  }

  /**
   * @Route("/logout", name="logout")
   */
  public function logoutAction(Request $request)
  {
  }
}