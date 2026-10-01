<?php
namespace mara\database;
/*------------------------------------------------------------------------------
** File:        mChat.php
** Class:       mChat
** Description: Manage chat data
** Version:     2.0
** Updated:     2026-09-28
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use mara\core\User;

class mChat
{
  private \mysqli $db;
  private string $table  = 'chat_sessions';
  private string $mtable = 'chat_messages';

public function __construct()
  {
    $this->db = Database::getInstance()->getConnection();
  }

/**
 * Get chat messages
 *
 * @access public
 * @param int $chatId
 * @return array
 */
public function getMessages(int $chatId): array
  {
    $stmt = $this->db->prepare(
        "SELECT *
         FROM ".$this->mtable."
         WHERE session_id = ?
         ORDER BY id ASC"
    );
    $stmt->bind_param(
        "i",
        $chatId
    );
    $stmt->execute();
    return $stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);
  }
/**
 * Get chat session
 *
 * @access public
 * @param int $chatId
 * @return array|null
 */
public function getChat(int $chatId): ?array
  {
    $userId = User::id();
    $stmt = $this->db->prepare(
        "SELECT *
         FROM ".$this->table."
         WHERE id = ?
         AND user_id = ?
         LIMIT 1"
    );
    $stmt->bind_param(
        "ii",
        $chatId,
        $userId
    );
    $stmt->execute();
    $chat = $stmt
        ->get_result()
        ->fetch_assoc();
    return $chat ?: null;
  }
/**
 * Get chat context state
 *
 * @access public
 * @param int $chatId
 * @return array
 */
public function getContextState(int $chatId): array
  {
    $chat = $this->getChat($chatId);

    if ($chat === null)
      {
        return [
          'prompt_eval_count' => 0,
          'context_trimmed'   => false
        ];
      }

    return [
      'prompt_eval_count' => (int)($chat['prompt_eval_count'] ?? 0),
      'context_trimmed'   => !empty($chat['context_trimmed'])
    ];
  }
/**
 * Save chat context state
 *
 * @access public
 * @param int $chatId
 * @param int $promptEvalCount
 * @param bool $contextTrimmed
 * @return bool
 */
public function saveContextState(
    int $chatId,
    int $promptEvalCount,
    bool $contextTrimmed
): bool
  {
    $userId = User::id();
    $trimmed = $contextTrimmed ? 1 : 0;

    $stmt = $this->db->prepare(
        "UPDATE ".$this->table."
         SET prompt_eval_count = ?,
             context_trimmed = ?
         WHERE id = ?
         AND user_id = ?"
    );

    $stmt->bind_param(
        "iiii",
        $promptEvalCount,
        $trimmed,
        $chatId,
        $userId
    );

    return $stmt->execute();
  }  
/**
 * Create chat session
 *
 * @access public
 * @param int $modelId
 * @param string $name
 * @return int
 */
public function createChat(int $modelId, string $name): int
  {
    $userId = User::id();

    $stmt = $this->db->prepare(
        "INSERT INTO ".$this->table."
          (user_id, model_id, session_name)
         VALUES (?, ?, ?)"
    );

    $stmt->bind_param(
        "iis",
        $userId,
        $modelId,
        $name
    );

    $stmt->execute();

    return (int)$this->db->insert_id;
  }
/**
 * Save chat message
 *
 * @access public
 * @param int $chatId
 * @param string $role
 * @param string $content
 * @param array $data
 * @return int
 */
public function saveMessage(
    int $chatId,
    string $role,
    string $content,
    array $data = []
): int
  {
    $imageUser      = $data['image_user'] ?? null;
    $imageGenerated = $data['image_generated'] ?? null;
    $imageSearched  = isset($data['image_searched'])
        ? json_encode($data['image_searched'], JSON_UNESCAPED_UNICODE)
        : null;

    $imagePrompt = $data['image_prompt'] ?? null;
    $ratingUser  = $data['rating_user'] ?? null;
    $ratingModel = $data['rating_model'] ?? null;

    $metrics = isset($data['metrics'])
        ? json_encode($data['metrics'], JSON_UNESCAPED_UNICODE)
        : null;

    $stmt = $this->db->prepare(
        "INSERT INTO ".$this->mtable."
          (
            session_id,
            role,
            content,
            image_user,
            image_generated,
            image_searched,
            image_prompt,
            rating_user,
            rating_model,
            metrics
          )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "isssssssss",
        $chatId,
        $role,
        $content,
        $imageUser,
        $imageGenerated,
        $imageSearched,
        $imagePrompt,
        $ratingUser,
        $ratingModel,
        $metrics
    );

    $stmt->execute();

    return (int)$this->db->insert_id;
  }
/**
 * Save user rating
 *
 * @access public
 * @param int $messageId
 * @param array $rating
 * @return bool
 */
public function saveUserRating(
    int $messageId,
    array $rating
): bool
  {
    $ratingJson = json_encode(
        $rating,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    $stmt = $this->db->prepare(
        "UPDATE ".$this->mtable."
         SET rating_user = ?
         WHERE id = ?"
    );

    $stmt->bind_param(
        "si",
        $ratingJson,
        $messageId
    );

    return $stmt->execute();
  }
/**
 * Get user rating
 *
 * @param int $messageId Message ID
 * @return array|null
 */
public function getUserRating(int $messageId): ?array
  {
    if ($messageId <= 0)
      {
        return null;
      }

    $stmt = $this->db->prepare(
        "SELECT rating_user
         FROM ".$this->mtable."
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "i",
        $messageId
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();

    if (
        !$row ||
        empty($row['rating_user'])
    )
      {
        return null;
      }

    $rating = json_decode(
        $row['rating_user'],
        true
    );

    return is_array($rating)
        ? $rating
        : null;
  }    
/**
 * Create conversation with initial messages
 *
 * @access public
 * @param int $modelId
 * @param string $name
 * @param string $systemPrompt
 * @param string $userMessage
 * @param array $userData
 * @return int
 */
  public function createConversation(
    int $modelId,
    string $name,
    string $systemPrompt,
    string $userMessage,
    array $userData = []
): array
  {
    $this->db->begin_transaction();

    try
      {
        $chatId = $this->createChat(
            $modelId,
            $name
        );

        if ($chatId <= 0)
          {
            throw new \RuntimeException('Chat session could not be created.');
          }

        $systemId = $this->saveMessage(
            $chatId,
            'system',
            $systemPrompt
        );

        if ($systemId <= 0)
          {
            throw new \RuntimeException('System message could not be saved.');
          }

        $userId = $this->saveMessage(
            $chatId,
            'user',
            $userMessage,
            $userData
        );

        if ($userId <= 0)
          {
            throw new \RuntimeException('User message could not be saved.');
          }

        $this->db->commit();

        return [
            'chat_id'    => $chatId,
            'message_id' => $userId
        ];
      }
    catch (\Throwable $e)
      {
        $this->db->rollback();

        return [
            'chat_id'    => 0,
            'message_id' => 0
        ];
      }
  }  

/**
 * Rename a chat session owned by the current user.
 *
 * @param int $chatId
 * @param string $name
 * @return bool
 */
public function renameChat(int $chatId, string $name): bool
  {
    $name = trim($name);

    if ($chatId <= 0 || $name === '')
      {
        return false;
      }

    $userId = User::id();

    $stmt = $this->db->prepare(
        "UPDATE ".$this->table."
         SET session_name = ?
         WHERE id = ?
         AND user_id = ?"
    );

    $stmt->bind_param(
        "sii",
        $name,
        $chatId,
        $userId
    );

    return $stmt->execute();
  }

/**
 * Delete a chat session and all of its messages.
 *
 * @param int $chatId
 * @return bool
 */
public function deleteChat(int $chatId): bool
  {
    if ($chatId <= 0 || $this->getChat($chatId) === null)
      {
        return false;
      }

    $userId = User::id();

    $this->db->begin_transaction();

    try
      {
        $messages = $this->db->prepare(
            "DELETE FROM ".$this->mtable."
             WHERE session_id = ?"
        );

        $messages->bind_param("i", $chatId);

        if (!$messages->execute())
          {
            throw new \RuntimeException(
                'Chat messages could not be deleted.'
            );
          }

        $chat = $this->db->prepare(
            "DELETE FROM ".$this->table."
             WHERE id = ?
             AND user_id = ?"
        );

        $chat->bind_param("ii", $chatId, $userId);

        if (
            !$chat->execute() ||
            $chat->affected_rows !== 1
        )
          {
            throw new \RuntimeException(
                'Chat session could not be deleted.'
            );
          }

        $this->db->commit();

        return true;
      }
    catch (\Throwable)
      {
        $this->db->rollback();

        return false;
      }
  }






/**
 * Get chat titles
 * @access public
 * @param int $modelId
 * @return array
 */
public function getHumanChatNames(int $modelId): array
  {
    $userId = User::id();

    $stmt = $this->db->prepare(
        "SELECT id, session_name
         FROM ".$this->table."
         WHERE model_id = ?
         AND user_id = ?
         ORDER BY created_at DESC"
    );

    $stmt->bind_param(
        "ii",
        $modelId,
        $userId
    );

    $stmt->execute();

    return $stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);
  }  
}
?>
