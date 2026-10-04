<?php
declare(strict_types=1);
namespace mara\database;
use mara\core\EmotionalState;

final class mEmotionalState
{
    private \mysqli $db;
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /** Per user and character, shared across that user's conversations. */
    public function get(int $userId, int $modelId): array
    {
        $stmt = $this->db->prepare('SELECT state_json FROM emotional_states WHERE user_id = ? AND model_id = ?');
        $stmt->bind_param('ii', $userId, $modelId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $state = json_decode((string)($row['state_json'] ?? ''), true);
        if (!is_array($state)) return EmotionalState::neutral();
        $validated = EmotionalState::validate($state + ['note' => 'Stored state']);
        return $validated['state'];
    }

    public function save(int $userId, int $modelId, int $chatId, array $state, string $note): bool
    {
        // The caller's session must actually belong to this user/model.
        $stmt = $this->db->prepare('SELECT id FROM chat_sessions WHERE id = ? AND user_id = ? AND model_id = ? LIMIT 1');
        $stmt->bind_param('iii', $chatId, $userId, $modelId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new \RuntimeException('Invalid emotional-state conversation context.');
        }
        $json = json_encode($state, JSON_THROW_ON_ERROR);
        $stmt = $this->db->prepare('INSERT INTO emotional_states (user_id, model_id, chat_id, state_json, note)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE chat_id = VALUES(chat_id), state_json = VALUES(state_json), note = VALUES(note), updated_at = UTC_TIMESTAMP()');
        $stmt->bind_param('iiiss', $userId, $modelId, $chatId, $json, $note);
        return $stmt->execute();
    }
}
