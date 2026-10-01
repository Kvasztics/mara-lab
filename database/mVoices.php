<?php
namespace mara\database;
/*------------------------------------------------------------------------------
** File:        mVoices.php
** Class:       mVoices
** Description: Manage voice data
** Version:     1.1
** Updated:     2026-09-26
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/

class mVoices 
{
  protected \mysqli $db;
  private string $table  = 'voices';
/**
 * Constructor
 * @access public
 * @return void
 */
public function __construct() 
  {
    $this->db = Database::getInstance()->getConnection();
  }
/**
 * Get voicedata by id
 * @access public
 * @param int $voiceId
 * @return array|null
 */
public function get(int $voiceId): array|null
  {
    $stmt = $this->db->prepare(
        "SELECT * FROM ".$this->table." WHERE id = ?  LIMIT 1"
    );
    $stmt->bind_param("i", $voiceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }
/**
 * Get voices
 * @access public
 * @param string|null $provider
 * @return array
 */
public function getAll(?string $provider = null): array
  {
    if ($provider === null)
      {
        $result = $this->db->query(
            "SELECT * FROM " . $this->table . " ORDER BY name"
        );
      }
    else
      {
        $stmt = $this->db->prepare(
            "SELECT * FROM " . $this->table . "
             WHERE provider = ?
             ORDER BY name"
        );

        $stmt->bind_param("s", $provider);
        $stmt->execute();

        $result = $stmt->get_result();
      }

    return $result->fetch_all(MYSQLI_ASSOC);
  }
/**
 * Insert voice
 * @access public
 * @param array $data
 * @return int
 */
public function insert(array $data): int
  {
    $stmt = $this->db->prepare(
        "INSERT INTO " . $this->table . "
            (provider, name, sample, reftext, parameters)
         VALUES (?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "sssss",
        $data['provider'],
        $data['name'],
        $data['sample'],
        $data['reftext'],
        $data['parameters']
    );

    $stmt->execute();

    return $this->db->insert_id;
  }
/**
 * Update voice
 * @access public
 * @param int $voiceId
 * @param array $data
 * @return bool
 */
public function update(int $voiceId, array $data): bool
  {
    $stmt = $this->db->prepare(
        "UPDATE " . $this->table . "
         SET provider = ?,
             name = ?,
             sample = ?,
             reftext = ?,
             parameters = ?
         WHERE id = ?"
    );

    $stmt->bind_param(
        "sssssi",
        $data['provider'],
        $data['name'],
        $data['sample'],
        $data['reftext'],
        $data['parameters'],
        $voiceId
    );

    return $stmt->execute();
  }
/**
 * Delete voice
 * @access public
 * @param int $voiceId
 * @return bool
 */
public function delete(int $voiceId): bool
  {
    $stmt = $this->db->prepare(
        "DELETE FROM " . $this->table . "
         WHERE id = ?"
    );

    $stmt->bind_param("i", $voiceId);

    return $stmt->execute();
  }  
}
?>