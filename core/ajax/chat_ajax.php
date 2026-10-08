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
/** Export only an owned conversation as a downloadable ShareGPT JSON dataset. */
public function exportchat(): void
{
    User::loggedIn();
    header('Cache-Control: private, no-store');
    try {
        $chatId = (int)($_GET['id'] ?? 0);
        $dataset = $this->CHAT->exportShareGpt($chatId);
        if ($dataset === null) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'A beszélgetés nem található vagy nem a tiéd.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $json = json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        // Clear incidental whitespace from included PHP files before downloading.
        while (ob_get_level() > 0) {
            if (!ob_end_clean()) break;
        }
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="mara-chat-'.$chatId.'-sharegpt.json"');
        echo $json;
        exit;
    } catch (\Throwable $error) {
        error_log('Mara conversation export: '.$error->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'A beszélgetés exportálása sikertelen.'], JSON_UNESCAPED_UNICODE);
    }
}

/** Read only the signed-in user's active character state. */
public function emotionalstate(): void
{
    User::loggedIn();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        $userId = (int)User::id();
        $modelId = (int)($_SESSION['model_id'] ?? 0);
        if ($userId <= 0 || $modelId <= 0) {
            echo json_encode(['success' => true, 'state' => null]);
            return;
        }
        $state = (new \mara\database\mEmotionalState())->get($userId, $modelId);
        echo json_encode(['success' => true, 'state' => $state]);
    } catch (\Throwable $error) {
        error_log('Emotional Ball display: '.$error->getMessage());
        echo json_encode(['success' => false, 'state' => null]);
    }
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

    try {
        $result = $this->CHAT->send($_POST);
    } catch (\Throwable $error) {
        error_log('Mara chat: '.$error->getMessage());
        http_response_code(500);
        $result = ['success' => false, 'error' => 'Az üzenet feldolgozása sikertelen. Ellenőrizd a szerver naplóját.'];
    }

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
    );
  }
/** Upload image separately so Send never races the upload. */
public function uploadimage(): void
{
    User::loggedIn();
    header('Content-Type: application/json; charset=utf-8');
    try {
        echo json_encode($this->CHAT->uploadImage($_FILES['image'] ?? []), JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $error) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/** Authenticated image delivery; uploaded files live outside the public directory. */
public function image(): void
{
    User::loggedIn();
    $file = $this->CHAT->imageFile('/chat_ajax/image?name='.(string)($_GET['name'] ?? ''));
    if ($file === null) { http_response_code(404); return; }
    // PHP includes can emit whitespace after closing tags. Never prepend it to binary data.
    while (ob_get_level() > 0) {
        if (!ob_end_clean()) { break; }
    }
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    header('Content-Type: '.$file['mime']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Length: '.filesize($file['path']));
    readfile($file['path']);
    exit;
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

    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))
            !== 'xmlhttprequest'
    ) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => LANG['CHAT_ERROR_START']]);
        return;
    }

    echo json_encode(
        $this->CHAT->start(),
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
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
