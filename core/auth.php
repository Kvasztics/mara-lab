<?php
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        auth.class.php
** Class:       auth
** Description: authentication procedures
** Version:     1.1
** Updated:     2026-07-17
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use \mara\core\User;

class auth
{
    use \mara\core\Render;

/**
 * Check login credentials
 * @access public
 * @return void
 */
public function login(): void
  {
    $pagedata = [
        'error' => $_SESSION['login_error'] ?? ''
    ];

    unset($_SESSION['login_error']);

    $this->show(array(
        "header"   => null,
        "page"     => DIR_TPL."/login.tpl.php",
        "footer"   => null,
        "html"     => false,
        "pagedata" => $pagedata
    ));    
  }
/**
 * Process login
 * @access public
 * @return void
 */
public function dologin(): void
  {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['pass'] ?? '';

    if ($email === '' || $pass === '')
      {
        $_SESSION['login_error'] = 'Add meg az e-mail címet és a jelszót.';
        header('Location: '.DIR_HOST.'/auth/login');
        exit;
      }

    $user = User::login($email, $pass);

    if (!$user)
      {
        $_SESSION['login_error'] = 'Hibás e-mail cím vagy jelszó.';
        header('Location: '.DIR_HOST.'/auth/login');
        exit;
      }

    header('Location: '.DIR_HOST.'/main/view');
    exit;
  }
/**
 * User logout
 * @access public
 * @return void
 */
public function logout(): void
  {
    User::logout();

    header('Location: '.DIR_HOST.'/auth/login');
    exit;
  }  
}
?>