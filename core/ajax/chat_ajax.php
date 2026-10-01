<?php
namespace mara\core\ajax;

use mara\core\User;
use mara\core\Chat;

class chat_ajax
{
  private Chat $CHAT;

  public function __construct()
    {
      $this->CHAT = new Chat();
    }
/**
 * Send chat message
 *
 * @access public
 * @return void
 */
public function sendmessage(): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    $result = $this->CHAT->send($_POST);

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
    );
  }
/**
 * Set new chat
 *
 * @access public
 * @return void
 */
public function newchat(): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    $this->CHAT->new();

    echo json_encode([
      'success' => true,
      'chat_id' => 0
    ]);
  }
/**
 * Change chat
 *
 * @access public
 * @return void
 */
  public function changechat(): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    $chatId = (int)($_POST['id'] ?? 0);

    if ($chatId <= 0)
      {
        echo json_encode([
          'success' => false
        ]);
        return;
      }

    $html = $this->CHAT->change($chatId);

    echo json_encode([
      'success' => true,
      'chat_id' => $chatId,
      'messages' => $html
    ]);
  }
/**
 * Rename chat
 *
 * @access public
 * @return void
 */
public function renamechat(): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    $chatId = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));

    $success = $chatId > 0 && $name !== ''
        && $this->CHAT->rename($chatId, $name);

    echo json_encode([
      'success' => $success,
      'chat_id' => $chatId,
      'name'    => $name
    ], JSON_UNESCAPED_UNICODE);
  }
/**
 * Delete chat
 *
 * @access public
 * @return void
 */
public function deletechat(): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    $chatId = (int)($_POST['id'] ?? 0);
    $wasActive = (int)($_SESSION['chat_id'] ?? 0) === $chatId;

    $success = $chatId > 0
        && $this->CHAT->delete($chatId);

    echo json_encode([
      'success' => $success,
      'chat_id' => $chatId,
      'was_active' => $wasActive
    ]);
  }
/**
 * get user rating data
 *
 * @access public
 * @return void
 */
public function getUserRating(): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    $messageId = (int)($_POST['id'] ?? 0);

    if ($messageId <= 0)
      {
        echo json_encode([
          'success' => false
        ]);
        return;
      }

    $rating = $this->CHAT->getUserRating($messageId);
    $html   = $this->CHAT->renderRating($rating);

    echo json_encode([
      'success' => true,
      'html' => $html
    ]);
  }  
}
?>
