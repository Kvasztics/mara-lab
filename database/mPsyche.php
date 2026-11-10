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
       ORDER BY prompt_modify DESC, id DESC"
    );

    $stmt->bind_param('i', $modelId);
    $stmt->execute();

    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($records as &$record) {
        $record['version'] = hash('sha256', serialize($record));
    }
    unset($record);
    return $records;
  }
/** Save under the model row lock held by the caller. */
public function saveSelection(
    int $modelId,
    int $selectedId,
    string $selectedVersion,
    bool $create,
    string $prompt,
    string $memory,
    string $reason
): bool
  {
    $records = $this->getByModelId($modelId);
    $latestModification = $records[0]['prompt_modify'] ?? null;
    [$prompt, $memory, $reason] = str_replace(["\r\n", "\r"], "\n", [$prompt, $memory, $reason]);

    if ($create || ($selectedId === 0 && !$records)) {
        $stmt = $this->DB->prepare(
            "INSERT INTO {$this->table} (model_id, prompt, memory, reason, prompt_modify)
             VALUES (?, ?, ?, ?, GREATEST(CURRENT_TIMESTAMP,
                 COALESCE(TIMESTAMPADD(SECOND, 1, CAST(? AS DATETIME)), CURRENT_TIMESTAMP)))"
        );
        $stmt->bind_param('issss', $modelId, $prompt, $memory, $reason, $latestModification);
        return $stmt->execute();
    }

    foreach ($records as $record) {
        if ($selectedId === 0 || (int)$record['id'] === $selectedId) {
            if ($selectedVersion !== '' && $selectedVersion !== $record['version']) {
                return false;
            }
            return $this->updateRecord($record, $prompt, $memory, $reason, $latestModification);
        }
    }
    return false;
  }

private function updateRecord(array $record, string $prompt, string $memory, string $reason, ?string $latestModification): bool
  {
    $normalize = static fn(?string $text): string => str_replace(["\r\n", "\r"], "\n", (string)$text);
    $memoryChanged = (int)($normalize($record['memory']) !== $normalize($memory));
    $prompt = $normalize($record['prompt']) === $normalize($prompt) ? $record['prompt'] : $prompt;
    $memory = $memoryChanged ? $memory : $record['memory'];
    $reason = $normalize($record['reason']) === $normalize($reason) ? $record['reason'] : $reason;
    $id = (int)$record['id'];
    $modelId = (int)$record['model_id'];
    $stmt = $this->DB->prepare(
        "UPDATE {$this->table}
         SET prompt = ?,
             prompt_modify = COALESCE(GREATEST(CURRENT_TIMESTAMP, TIMESTAMPADD(SECOND, 1, CAST(? AS DATETIME))), prompt_modify),
             memory = ?, memory_modify = IF(?, CURRENT_TIMESTAMP, memory_modify),
             reason = ?
         WHERE id = ? AND model_id = ?"
    );
    $stmt->bind_param('sssisii', $prompt, $latestModification, $memory, $memoryChanged, $reason, $id, $modelId);
    return $stmt->execute();
  }

/** Update the memory of the current Psyché without changing its prompt. */
public function updateLatestMemory(int $modelId, string $memory, string $mode = 'append', string $reason = ''): bool
  {
    if (!$this->DB->begin_transaction()) {
        return false;
    }
    $committed = false;
    try {
        $lock = $this->DB->prepare('SELECT id FROM models WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $modelId);
        if (!$lock->execute() || !$lock->get_result()->fetch_assoc()) {
            return false;
        }
        $current = $this->getByModelId($modelId)[0] ?? null;
        if ($current === null) {
            return false;
        }

        $currentMemory = trim((string)$current['memory']);
        $newMemory = $mode === 'replace' ? trim($memory) : trim($currentMemory . "\n\n" . trim($memory));
        $currentReason = (string)$current['reason'];
        $newReason = trim($reason) === '' ? $currentReason : trim($reason);
        if (($newMemory !== $currentMemory || $newReason !== $currentReason) &&
            !$this->updateRecord($current, (string)$current['prompt'], $newMemory, $newReason, null)) {
            return false;
        }
        $committed = $this->DB->commit();
        return $committed;
    } finally {
        if (!$committed) $this->DB->rollback();
    }
  }
}
?>
