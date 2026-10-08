<?php
declare(strict_types=1);
namespace mara\database;

trait ChatTurns
{
    /** Read an owned conversation and identify a complete user/assistant turn. */
    public function assistantTurn(int $chatId, int $modelId, int $assistantId): ?array
    {
        $chat = $this->getChat($chatId);
        if ($chat === null || (int)$chat['model_id'] !== $modelId) {
            return null;
        }
        $rows = $this->getMessages($chatId);
        $assistantIndex = null;
        foreach ($rows as $i => $row) {
            if ((int)$row['id'] === $assistantId && $row['role'] === 'assistant') {
                $assistantIndex = $i;
                break;
            }
        }
        if ($assistantIndex === null) return null;

        $userIndex = null;
        for ($i = $assistantIndex - 1; $i >= 0; --$i) {
            if ($rows[$i]['role'] === 'user') {
                $userIndex = $i;
                break;
            }
        }
        // An imported opening greeting has no preceding user turn.
        if ($userIndex === null) return null;
        $end = count($rows);
        for ($i = $userIndex + 1; $i < count($rows); ++$i) {
            if ($rows[$i]['role'] === 'user') {
                $end = $i;
                break;
            }
        }
        return [
            'rows' => $rows,
            'user' => $rows[$userIndex],
            'assistant' => $rows[$assistantIndex],
            'turn_ids' => array_map(
                static fn(array $row): int => (int)$row['id'],
                array_slice($rows, $userIndex, $end - $userIndex)
            ),
            'latest' => $assistantIndex === count($rows) - 1,
            'history' => array_slice($rows, 0, $userIndex + 1),
            'fingerprint' => hash('sha256', serialize($rows)),
        ];
    }

    /** Commit only if the owned conversation has not changed since it was read. */
    private function lockTurn(int $chatId, int $modelId, array $turn): void
    {
        $userId = \mara\core\User::id();
        $stmt = $this->db->prepare(
            "SELECT id FROM {$this->table}
             WHERE id = ? AND user_id = ? AND model_id = ? FOR UPDATE"
        );
        $stmt->bind_param('iii', $chatId, $userId, $modelId);
        if (!$stmt->execute() || !$stmt->get_result()->fetch_assoc()) {
            throw new \RuntimeException('Conversation is not available.');
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->mtable} WHERE session_id = ? ORDER BY id ASC FOR UPDATE"
        );
        $stmt->bind_param('i', $chatId);
        if (!$stmt->execute()) throw new \RuntimeException('Cannot read conversation.');
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!hash_equals($turn['fingerprint'], hash('sha256', serialize($rows)))) {
            throw new \RuntimeException('Conversation changed during the request.');
        }
    }

    public function replaceAssistantTurn(
        int $chatId,
        int $modelId,
        array $turn,
        string $content,
        array $data,
        ?array $rating,
        int $promptTokens,
        bool $trimmed
    ): int {
        $this->db->begin_transaction();
        try {
            $this->lockTurn($chatId, $modelId, $turn);
            if (empty($turn['latest'])) {
                throw new \RuntimeException('Only the latest response can be regenerated.');
            }
            $id = (int)$turn['assistant']['id'];
            $image = $data['image_generated'] ?? null;
            $prompt = $data['image_prompt'] ?? null;
            $metrics = json_encode($data['metrics'] ?? [], JSON_THROW_ON_ERROR);
            $stmt = $this->db->prepare(
                "UPDATE {$this->mtable}
                 SET content = ?, image_generated = ?, image_prompt = ?, metrics = ?,
                     image_searched = NULL, rating_user = NULL, rating_model = NULL
                 WHERE id = ? AND session_id = ? AND role = 'assistant'"
            );
            $stmt->bind_param('ssssii', $content, $image, $prompt, $metrics, $id, $chatId);
            if (!$stmt->execute()) throw new \RuntimeException('Cannot replace response.');

            $ratingJson = $rating === null ? null : json_encode($rating, JSON_THROW_ON_ERROR);
            $userMessageId = (int)$turn['user']['id'];
            $stmt = $this->db->prepare(
                "UPDATE {$this->mtable} SET rating_user = ? WHERE id = ? AND session_id = ?"
            );
            $stmt->bind_param('sii', $ratingJson, $userMessageId, $chatId);
            if (!$stmt->execute() || !$this->saveContextState($chatId, $promptTokens, $trimmed)) {
                throw new \RuntimeException('Cannot update response metadata.');
            }
            if (!$this->db->commit()) throw new \RuntimeException('Cannot commit response.');
            return $id;
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    public function deleteAssistantTurn(int $chatId, int $modelId, array $turn): bool
    {
        $this->db->begin_transaction();
        try {
            $this->lockTurn($chatId, $modelId, $turn);
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->mtable} WHERE id = ? AND session_id = ?"
            );
            foreach ($turn['turn_ids'] as $id) {
                $stmt->bind_param('ii', $id, $chatId);
                if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                    throw new \RuntimeException('Cannot delete conversation turn.');
                }
            }
            if (!$this->saveContextState($chatId, 0, false)) {
                throw new \RuntimeException('Cannot reset context measurements.');
            }
            if (!$this->db->commit()) throw new \RuntimeException('Cannot commit deletion.');
            return true;
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }
}
