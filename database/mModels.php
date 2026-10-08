<?php
declare(strict_types=1);
namespace mara\database;
/*------------------------------------------------------------------------------
** File:        mModels.class.php
** Class:       mModels
** Description: Manage models data
** Version:     1.1
** Updated:     2026-09-27
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/


final class mModels
{
  private string $table = 'models';
  private \mysqli $DB;

public function __construct()
  {
      $this->DB = Database::getInstance()->getConnection();
  }
/**
 * Get model by ID.
 */
public function getById(int $id): ?array
  {
      $stmt = $this->DB->prepare(
          "SELECT *
            FROM {$this->table}
            WHERE id = ?
            LIMIT 1"
      );

      $stmt->bind_param('i', $id);
      $stmt->execute();

      $row = $stmt->get_result()->fetch_assoc();

      return $row ?: null;
  }    
/**
 * Get first model by provider.
 */	
public function getFirstPublicByProvider(string $provider): ?array
	{
		$stmt = $this->DB->prepare(
			"SELECT *
			 FROM {$this->table}
			 WHERE user_id = 0
			   AND provider = ?
			 ORDER BY id ASC
			 LIMIT 1"
		);

		$stmt->bind_param('s', $provider);
		$stmt->execute();

		$row = $stmt->get_result()->fetch_assoc();

		return $row ?: null;
	}
/**
 * Get first user model by provider.
 * @access public
 * @param string $provider
 * @param int $userId
 * @return array|null
 */
public function getFirstUserByProvider(string $provider, int $userId): ?array
  {
    $stmt = $this->DB->prepare(
      "SELECT *
       FROM {$this->table}
       WHERE user_id = ?
         AND provider = ?
       ORDER BY id ASC
       LIMIT 1"
    );

    $stmt->bind_param('is', $userId, $provider);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return $row ?: null;
  }  	
/**
 * Get models
 * @access public
 * @param int $userId
 * @param bool $admin
 * @param string|null $provider
 * @return array
 */
public function getAll(
    int $userId,
    bool $admin = false,
    ?string $provider = null
): array
  {
    /*
     * Administrator can see all models
     */
    if ($admin)
      {
        if ($provider === null)
          {
            $result = $this->DB->query(
                "SELECT * FROM " . $this->table . "
                 ORDER BY name"
            );
          }
        else
          {
            $stmt = $this->DB->prepare(
                "SELECT * FROM " . $this->table . "
                 WHERE provider = ?
                 ORDER BY name"
            );
            $stmt->bind_param(
                "s",
                $provider
            );
            $stmt->execute();
            $result = $stmt->get_result();
          }
      }
    /*
     * User can see public and own models
     */
    else
      {
        if ($provider === null)
          {
            $stmt = $this->DB->prepare(
                "SELECT * FROM " . $this->table . "
                 WHERE user_id = 0
                    OR user_id = ?
                 ORDER BY name"
            );
            $stmt->bind_param(
                "i",
                $userId
            );
          }
        else
          {
            $stmt = $this->DB->prepare(
                "SELECT * FROM " . $this->table . "
                 WHERE (user_id = 0 OR user_id = ?)
                   AND provider = ?
                 ORDER BY name"
            );
            $stmt->bind_param(
                "is",
                $userId,
                $provider
            );
          }
        $stmt->execute();
        $result = $stmt->get_result();
      }
    return $result->fetch_all(
        MYSQLI_ASSOC
    );
  }
/**
 * Update model
 * @access public
 * @param int $id
 * @param array $data
 * @return bool
 */
  public function update(int $id, array $data): bool
  {
    $data['card_data'] = $data['card_data'] ?? null;
    $stmt = $this->DB->prepare(
      "UPDATE {$this->table}
       SET provider = ?,
           basemodel = ?,
           mmproj = ?,
           user_id = ?,
           voice_id = ?,
           name = ?,
           note = ?,
           prompt = ?,
           image = ?,
           thinking = ?,
           psyche = ?,
           rag = ?,
           rag_similarity = ?,
           rag_limit = ?,
           parameters = ?,
           modelinfo = ?,
           rag_ids = ?,
           card_data = COALESCE(?, card_data)
       WHERE id = ?"
    );

    $stmt->bind_param(
      'sssiissssiiidissssi',
      $data['provider'],
      $data['basemodel'],
      $data['mmproj'],
      $data['user_id'],
      $data['voice_id'],
      $data['name'],
      $data['note'],
      $data['prompt'],
      $data['image'],
      $data['thinking'],
      $data['psyche'],
      $data['rag'],
      $data['rag_similarity'],
      $data['rag_limit'],
      $data['parameters'],
      $data['modelinfo'],
      $data['rag_ids'],
      $data['card_data'],
      $id
    );

    return $stmt->execute();
  }
/**
 * Insert model
 *
 * @param array $data
 * @return int New model ID, or 0 on failure
 */
public function insert(array $data): int
  {
    $data['card_data'] = $data['card_data'] ?? null;
    $stmt = $this->DB->prepare(
      "INSERT INTO {$this->table}
       (
         provider, basemodel, mmproj, user_id, voice_id, name, note,
         prompt, image, thinking, psyche, rag, rag_similarity, rag_limit,
         parameters, modelinfo, rag_ids, card_data
       )
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
      'sssiissssiiidissss',
      $data['provider'],
      $data['basemodel'],
      $data['mmproj'],
      $data['user_id'],
      $data['voice_id'],
      $data['name'],
      $data['note'],
      $data['prompt'],
      $data['image'],
      $data['thinking'],
      $data['psyche'],
      $data['rag'],
      $data['rag_similarity'],
      $data['rag_limit'],
      $data['parameters'],
      $data['modelinfo'],
      $data['rag_ids'],
      $data['card_data']
    );

    if (!$stmt->execute())
      {
        return 0;
      }

    return (int)$this->DB->insert_id;
  }
/** Update only metadata, conditional on the model source remaining unchanged. */
public function updateModelInfo(int $id, array $info, array $source): bool
  {
    $json = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $provider = (string)($source['provider'] ?? '');
    $baseModel = (string)($source['basemodel'] ?? '');
    $mmproj = (string)($source['mmproj'] ?? '');
    $stmt = $this->DB->prepare(
      "UPDATE {$this->table} SET modelinfo = ?
       WHERE id = ? AND provider = ? AND basemodel = ? AND COALESCE(mmproj, '') = ?"
    );
    $stmt->bind_param('sisss', $json, $id, $provider, $baseModel, $mmproj);
    return $stmt->execute() && $stmt->affected_rows > 0;
  }
/**
 * Delete model
 *
 * @param int $id
 * @return bool
 */
public function delete(int $id): bool
  {
    $stmt = $this->DB->prepare(
      "DELETE FROM {$this->table}
       WHERE id = ?"
    );

    $stmt->bind_param('i', $id);

    return $stmt->execute();
  }    
}
?>
