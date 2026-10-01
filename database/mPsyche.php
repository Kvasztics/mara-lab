<?php
declare(strict_types=1);
namespace mara\database;
/*------------------------------------------------------------------------------
** File:        mPsyche.class.php
** Class:       mPsyche
** Description: Manage models secondary system prompt
** Version:     1.1
** Updated:     2026-09-27
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/


final class mPsyche
{
  private string $table = 'models_psyche';
  private \mysqli $DB;

public function __construct()
  {
      $this->DB = Database::getInstance()->getConnection();
  }
/**
 * Get psyche records by model ID
 *
 * @param int $modelId
 * @return array
 */
public function getByModelId(int $modelId): array
  {
    $stmt = $this->DB->prepare(
      "SELECT *
       FROM {$this->table}
       WHERE model_id = ?
       ORDER BY id DESC"
    );

    $stmt->bind_param('i', $modelId);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }
/**
 * Insert psyche record.
 *
 * @param int $modelId
 * @param string $prompt
 * @param string $memory
 * @param string $reason
 * @return bool
 */
public function insert(
    int $modelId,
    string $prompt,
    string $memory,
    string $reason
): bool
  {
    $stmt = $this->DB->prepare(
      "INSERT INTO {$this->table}
       (model_id, prompt, memory, reason)
       VALUES (?, ?, ?, ?)"
    );

    $stmt->bind_param(
      'isss',
      $modelId,
      $prompt,
      $memory,
      $reason
    );

    return $stmt->execute();
  }
/**
 * Update latest psyche record.
 *
 * prompt_modify is changed only when prompt changes.
 * memory_modify is changed only when memory changes.
 *
 * @param int $modelId
 * @param string $prompt
 * @param string $memory
 * @param string $reason
 * @return bool
 */
public function updateLatest(
    int $modelId,
    string $prompt,
    string $memory,
    string $reason
): bool
  {
    $records = $this->getByModelId($modelId);

    if (empty($records))
      {
        return $this->insert(
          $modelId,
          $prompt,
          $memory,
          $reason
        );
      }

    $current = $records[0];

    $promptChanged = (string)($current['prompt'] ?? '') !== $prompt;
    $memoryChanged = (string)($current['memory'] ?? '') !== $memory;
    $reasonChanged = (string)($current['reason'] ?? '') !== $reason;

    if (!$promptChanged && !$memoryChanged && !$reasonChanged)
      {
        return true;
      }

    $set   = [];
    $types = '';
    $values = [];

    if ($promptChanged)
      {
        $set[]    = 'prompt = ?';
        $set[]    = 'prompt_modify = CURRENT_TIMESTAMP';
        $types   .= 's';
        $values[] = $prompt;
      }

    if ($memoryChanged)
      {
        $set[]    = 'memory = ?';
        $set[]    = 'memory_modify = CURRENT_TIMESTAMP';
        $types   .= 's';
        $values[] = $memory;
      }

    if ($reasonChanged)
      {
        $set[]    = 'reason = ?';
        $types   .= 's';
        $values[] = $reason;
      }

    $types   .= 'i';
    $values[] = (int)$current['id'];

    $sql = "UPDATE {$this->table}
            SET " . implode(', ', $set) . "
            WHERE id = ?
            LIMIT 1";

    $stmt = $this->DB->prepare($sql);
    $stmt->bind_param($types, ...$values);

    return $stmt->execute();
  }

/**
 * Update the memory of the latest psyche record without changing its prompt.
 *
 * @param int $modelId
 * @param string $memory
 * @param string $mode append or replace
 * @param string $reason
 * @return bool
 */
public function updateLatestMemory(
    int $modelId,
    string $memory,
    string $mode = 'append',
    string $reason = ''
): bool
  {
    $records = $this->getByModelId($modelId);

    if (empty($records))
      {
        return false;
      }

    $current = $records[0];
    $currentMemory = trim((string)($current['memory'] ?? ''));
    $memory = trim($memory);

    if ($mode !== 'replace')
      {
        $mode = 'append';
      }

    $newMemory = $mode === 'replace'
        ? $memory
        : trim($currentMemory . "\n\n" . $memory);

    $reason = trim($reason);

    if (
        $newMemory === $currentMemory &&
        ($reason === '' || $reason === (string)($current['reason'] ?? ''))
    )
      {
        return true;
      }

    if ($reason === '')
      {
        $stmt = $this->DB->prepare(
            "UPDATE {$this->table}
             SET memory = ?,
                 memory_modify = CURRENT_TIMESTAMP
             WHERE id = ?
             LIMIT 1"
        );

        $id = (int)$current['id'];
        $stmt->bind_param('si', $newMemory, $id);

        return $stmt->execute();
      }

    $stmt = $this->DB->prepare(
        "UPDATE {$this->table}
         SET memory = ?,
             memory_modify = CURRENT_TIMESTAMP,
             reason = ?
         WHERE id = ?
         LIMIT 1"
    );

    $id = (int)$current['id'];
    $stmt->bind_param('ssi', $newMemory, $reason, $id);

    return $stmt->execute();
  }


}
?>
