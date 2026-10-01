<?php
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        User.class.php
** Class:       User
** Description: User login data
** Version:     1.1
** Updated:     2026-07-17
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use \mara\database\mUsers;

class User
{
/**
 * Is the user logged in?
 * @access public
 * @return bool
 */
public static function loggedIn(): bool
  {
    if (empty($_SESSION['user']['id']))
      {
        if (self::isAjax())
          {
            header('Content-Type: application/json; charset=utf-8');

            http_response_code(401);

            echo json_encode([
                'error'    => 'LOGIN_REQUIRED',
                'redirect' => DIR_HOST.'/auth/login'
            ]);

            exit;
          }
        header('Location: '.DIR_HOST.'/auth/login');
        exit;
      }
    return true;
  }
/**
 * Get user data
 * @access public
 * @return array
 */
public static function get(): array
  {
    return $_SESSION['user'] ?? [];
  }
/**
 * User access control
 * @access public
 * @return bool
 */
public static function isAdmin(): bool
  {
    return ($_SESSION['user']['role'] ?? '') === 'admin';
  }  
/**
 * Get user id
 * @access public
 * @return int
 */
public static function id(): int
  {
    return (int)($_SESSION['user']['id'] ?? 0);
  }
/**
 * Get user email
 * @access public
 * @return string
 */
public static function email(): string
  {
    return $_SESSION['user']['email'] ?? '';
  } 
/**
 * Get user name
 * @access public
 * @return string
 */
public static function name(): string
  {
    return $_SESSION['user']['name'] ?? '';
  }   
/**
 * User login
 * @access public
 * @param string $email
 * @param string $pass
 * @return array|null
 */
public static function login(string $email, string $pass): array|null
  {
    $MU   = new mUsers();
    $user = $MU->login($email, $pass);
    if (!$user)
      {
        return null;
      }      
    session_regenerate_id(true);
    unset($user['pass']);
    $_SESSION['user'] = $user;
    unset($_SESSION['model_id']);
    return $user;
  }
/**
 * User logout
 * @access public
 * @return void
 */
public static function logout(): void
  {
    unset($_SESSION['user']);
    session_regenerate_id(true);    
  }
/**
 * Is current request AJAX?
 * @access private
 * @return bool
 */
private static function isAjax(): bool
  {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
  }  
}
?>