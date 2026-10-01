<?php
namespace mara\database;
/*------------------------------------------------------------------------------
** File:        mRag.php
** Class:       mRag
** Description: Manage RAG data
** Version:     2.0
** Updated:     2026-09-27
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
class mRag
{
    private \mysqli $db;

    public function __construct()
      {
        $this->db = Database::getInstance()->getConnection();
      }

    /**
     * Create RAG document
     * @param int $userId
     * @param string $title
     * @param string|null $source
     * @return int
     */
    public function createDocument(
        int $userId,
        string $title,
        ?string $source = null
    ): int
      {
        $stmt = $this->db->prepare(
            "INSERT INTO rag_documents
                (user_id, title, source)
             VALUES (?, ?, ?)"
        );

        $stmt->bind_param(
            "iss",
            $userId,
            $title,
            $source
        );

        $stmt->execute();

        $id = $this->db->insert_id;
        $stmt->close();

        return $id;
      }

    /**
     * Save RAG chunk
     * @param int $documentId
     * @param int $chunkIndex
     * @param string $content
     * @param array $embedding
     * @return int
     */
    public function saveChunk(
        int $documentId,
        int $chunkIndex,
        string $content,
        array $embedding
    ): int
      {
        $json = json_encode(
            $embedding,
            JSON_UNESCAPED_UNICODE
        );

        $stmt = $this->db->prepare(
            "INSERT INTO rag_chunks
                (document_id, chunk_index, content, embedding)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "iiss",
            $documentId,
            $chunkIndex,
            $content,
            $json
        );

        $stmt->execute();

        $id = $this->db->insert_id;
        $stmt->close();

        return $id;
      }

/**
 * Get RAG documents
 *
 * If userId is given, return only user's documents.
 * If userId is null, return all documents.
 *
 * @param int|null $userId
 * @return array
 */
public function getDocuments(?int $userId = null): array
  {
    $sql = "
        SELECT
            d.id,
            d.user_id,
            d.title,
            d.source,
            d.created_at,
            (
                SELECT c.content
                FROM rag_chunks c
                WHERE c.document_id = d.id
                ORDER BY c.chunk_index ASC
                LIMIT 1
            ) AS content
        FROM rag_documents d
    ";

    if ($userId !== null)
      {
        $sql .= " WHERE d.user_id = ?";
      }

    $sql .= " ORDER BY d.id DESC";

    $stmt = $this->db->prepare($sql);

    if ($userId !== null)
      {
        $stmt->bind_param("i", $userId);
      }

    $stmt->execute();

    $rows = $stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    return $rows;
  }

    /**
     * Get enabled chunks from selected RAG documents
     * @param int $userId
     * @param array $ragIds
     * @return array
     */
    public function getChunks(int $userId, array $ragIds): array
      {
        $ragIds = array_values(array_unique(array_filter(
            array_map('intval', $ragIds),
            static fn(int $id): bool => $id > 0
        )));

        if (empty($ragIds))
          {
            return [];
          }

        $placeholders = implode(',', array_fill(0, count($ragIds), '?'));
        $types        = 'i'.str_repeat('i', count($ragIds));
        $params       = array_merge([$userId], $ragIds);

        $sql = "SELECT
                    c.id,
                    c.document_id,
                    c.chunk_index,
                    c.content,
                    c.embedding,
                    d.title
                FROM rag_chunks c
                INNER JOIN rag_documents d
                    ON d.id = c.document_id
                WHERE d.user_id = ?
                  AND d.id IN ($placeholders)
                  AND c.enabled = 1
                ORDER BY d.id, c.chunk_index";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $rows = $stmt
            ->get_result()
            ->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        foreach ($rows as &$row)
          {
            $row['embedding'] = json_decode($row['embedding'], true) ?? [];
          }
        unset($row);

        return $rows;
      }

/**
 * Delete RAG document and its chunks
 *
 * If userId is given, only the user's document can be deleted.
 * If userId is null, delete regardless of owner.
 *
 * @param int $documentId
 * @param int|null $userId
 * @return bool
 */
public function deleteDocument(
    int $documentId,
    ?int $userId = null
): bool
  {
    $this->db->begin_transaction();

    try
      {
        /*
         * Delete chunks
         */
        if ($userId !== null)
          {
            $stmt = $this->db->prepare(
                "DELETE c
                 FROM rag_chunks c
                 INNER JOIN rag_documents d
                    ON d.id = c.document_id
                 WHERE d.id = ?
                   AND d.user_id = ?"
            );

            $stmt->bind_param(
                "ii",
                $documentId,
                $userId
            );
          }
        else
          {
            $stmt = $this->db->prepare(
                "DELETE FROM rag_chunks
                 WHERE document_id = ?"
            );

            $stmt->bind_param(
                "i",
                $documentId
            );
          }

        $stmt->execute();
        $stmt->close();

        /*
         * Delete document
         */
        if ($userId !== null)
          {
            $stmt = $this->db->prepare(
                "DELETE FROM rag_documents
                 WHERE id = ?
                   AND user_id = ?"
            );

            $stmt->bind_param(
                "ii",
                $documentId,
                $userId
            );
          }
        else
          {
            $stmt = $this->db->prepare(
                "DELETE FROM rag_documents
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "i",
                $documentId
            );
          }

        $stmt->execute();

        $deleted = $stmt->affected_rows > 0;

        $stmt->close();

        $this->db->commit();

        return $deleted;
      }
    catch (\Throwable $e)
      {
        $this->db->rollback();
        throw $e;
      }
  }
/**
 * Begin transaction
 *
 * @return void
 */
public function begin(): void
  {
    $this->db->begin_transaction();
  }

/**
 * Commit transaction
 *
 * @return void
 */
public function commit(): void
  {
    $this->db->commit();
  }

/**
 * Rollback transaction
 *
 * @return void
 */
public function rollback(): void
  {
    $this->db->rollback();
  }  
}
?>