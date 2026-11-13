<?php

declare(strict_types=1);

namespace mara\database;

final class ChatMemory
{
    private \mysqli $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public static function enabledModelCondition(): string
    {
        return "JSON_UNQUOTE(JSON_EXTRACT(
            CASE WHEN JSON_VALID(m.parameters) THEN m.parameters ELSE '{}' END,
            '$.memory_enabled'
        )) = 'true'";
    }

    public function active(int $userId, int $modelId, int $chatId): array
    {
        return $this->db->execute_query(
            "SELECT m.id, m.content, m.kind
             FROM chat_memory m
             WHERE m.user_id = ? AND m.model_id = ?
               AND m.superseded_at IS NULL
             AND (m.kind <> 'project' OR EXISTS (SELECT 1 FROM chat_messages src WHERE src.id = m.source_message_id AND src.session_id = ?))
             ORDER BY m.id DESC LIMIT 8",
            [$userId, $modelId, $chatId]
        )->fetch_all(MYSQLI_ASSOC);
    }

    public function profiles(int $userId, int $modelId): array
    {
        return $this->db->execute_query(
            "SELECT content FROM chat_memory
             WHERE user_id = ? AND model_id = ? AND superseded_at IS NULL AND kind = 'profile'
             ORDER BY id DESC LIMIT 8",
            [$userId, $modelId]
        )->fetch_all(MYSQLI_ASSOC);
    }

    public function indexed(int $userId, int $modelId, string $key, int $chatId, bool $sparse = false): array
    {
        $vector = $sparse ? 'splade_vector' : 'embedding';
        $condition = $sparse ? 'splade_vector IS NOT NULL' : 'embedding_key = ?';
        $params = $sparse ? [$userId, $modelId, $chatId] : [$userId, $modelId, $key, $chatId];
        return $this->db->execute_query(
            "SELECT id, content, {$vector} AS embedding FROM chat_memory
             WHERE user_id = ? AND model_id = ? AND superseded_at IS NULL
               AND {$condition}
               AND (kind = 'context' OR (kind = 'project' AND EXISTS (
                   SELECT 1 FROM chat_messages src WHERE src.id = chat_memory.source_message_id AND src.session_id = ?
               )))",
             $params
        )->fetch_all(MYSQLI_ASSOC);
    }

    public function pendingIndex(string $key): iterable
    {
        $result = $this->db->execute_query(
            "SELECT id, content FROM chat_memory
             WHERE superseded_at IS NULL AND kind IN ('context', 'project')
               AND (embedding_key IS NULL OR embedding_key <> ?)
             ORDER BY id",
            [$key]
        );
        while ($row = $result->fetch_assoc()) {
            yield $row;
        }
    }

    public function storeVector(int $id, string $key, string $vector): void
    {
        $this->db->execute_query(
            'UPDATE chat_memory SET embedding_key = ?, embedding = ?
             WHERE id = ? AND superseded_at IS NULL',
            [$key, $vector, $id]
        );
    }

    public function source(int $messageId, bool $lock = false): array
    {
        $sql = "SELECT s.user_id, s.model_id, m.session_id, m.content
                FROM chat_messages m JOIN chat_sessions s ON s.id = m.session_id
                WHERE m.id = ? AND m.role = 'user'";
        $row = $this->db->execute_query($sql . ($lock ? ' FOR UPDATE' : ''), [$messageId])->fetch_assoc();
        if ($row === null) {
            throw new \RuntimeException('User message not found.');
        }
        return $row;
    }

    public function reviews(int $userId, int $modelId, ?int $messageId = null): array
    {
        $sql = "SELECT j.message_id, j.proposal, msg.content AS source,
                       old.content AS previous, COUNT(*) OVER () AS total
                FROM chat_memory_jobs j
                JOIN chat_messages msg ON msg.id = j.message_id
                JOIN chat_sessions s ON s.id = msg.session_id
                LEFT JOIN chat_memory old
                  ON old.id = JSON_UNQUOTE(JSON_EXTRACT(j.proposal, '$.memory_id'))
                 AND old.user_id = s.user_id AND old.model_id = s.model_id
                 AND old.superseded_at IS NULL AND old.source_message_id < j.message_id
                WHERE j.status = 'review' AND s.user_id = ? AND s.model_id = ?
                  AND NOT EXISTS (SELECT 1 FROM chat_memory saved WHERE saved.source_message_id = j.message_id)";
        $params = [$userId, $modelId];
        if ($messageId !== null) {
            $sql .= ' AND j.message_id = ?';
            $params[] = $messageId;
        }
        return $this->db->execute_query($sql . ' ORDER BY j.message_id LIMIT 1', $params)->fetch_assoc() ?? [];
    }

    public function dismiss(int $messageId, int $userId, int $modelId): void
    {
        $this->db->execute_query(
            "UPDATE chat_memory_jobs j JOIN chat_messages msg ON msg.id = j.message_id
             JOIN chat_sessions s ON s.id = msg.session_id
             SET j.status = 'dismissed'
             WHERE j.message_id = ? AND j.status = 'review' AND s.user_id = ? AND s.model_id = ?",
            [$messageId, $userId, $modelId]
        );
        if ($this->db->affected_rows !== 1) {
            throw new \RuntimeException('Memory proposal is no longer available.');
        }
    }

    public function save(int $messageId, string $content, ?int $replaces, string $kind, ?string $key, ?string $vector, ?array $reviewScope = null, ?string $automaticProposal = null): void
    {
        $this->db->begin_transaction();
        try {
            $source = $this->source($messageId, true);
            if ($reviewScope !== null) {
                if ((int)$source['user_id'] !== $reviewScope[0] || (int)$source['model_id'] !== $reviewScope[1]) {
                    throw new \RuntimeException('Memory proposal is outside the current scope.');
                }
                $this->db->execute_query(
                    "UPDATE chat_memory_jobs SET status = 'approved' WHERE message_id = ? AND status = 'review'",
                    [$messageId]
                );
                if ($this->db->affected_rows !== 1) {
                    throw new \RuntimeException('Memory proposal is no longer available.');
                }
            }
            if ($automaticProposal !== null) {
                $this->db->execute_query(
                    "UPDATE chat_memory_jobs SET status = 'approved', proposal = ?, last_error = NULL
                     WHERE message_id = ? AND status = 'pending'",
                    [$automaticProposal, $messageId]
                );
                if ($this->db->affected_rows !== 1) {
                    throw new \RuntimeException('Memory job is no longer pending.');
                }
            }
            if ($replaces !== null) {
                $previous = $this->db->execute_query(
                    "SELECT id FROM chat_memory
                     WHERE id = ? AND user_id = ? AND model_id = ? AND source_message_id < ?
                       AND superseded_at IS NULL
                       AND (kind <> 'project' OR EXISTS (SELECT 1 FROM chat_messages src WHERE src.id = chat_memory.source_message_id AND src.session_id = ?))
                     FOR UPDATE",
                    [$replaces, $source['user_id'], $source['model_id'], $messageId, $source['session_id']]
                )->fetch_assoc();
                if ($previous === null) {
                    throw new \RuntimeException('Replacement must target an earlier memory in the same scope.');
                }
                $this->db->execute_query(
                    'UPDATE chat_memory SET superseded_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [$replaces]
                );
            }
            $this->db->execute_query(
                'INSERT INTO chat_memory (user_id, model_id, source_message_id, supersedes_id, content, kind, embedding_key, embedding)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$source['user_id'], $source['model_id'], $messageId, $replaces, $content, $kind, $key, $vector]
            );
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }
}
