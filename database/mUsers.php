<?php
namespace mara\database;
/*------------------------------------------------------------------------------
** File:        mUsers.class.php
** Class:       mUsers
** Description: Manage users data
** Version:     1.1
** Updated:     2026-07-17
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/

class mUsers 
{
  protected \mysqli $db;
  private string $table  = 'users';
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
 * Get userdata by id or email
 * @access public
 * @param int|string $userId
 * @return array|null
 */
private function get(int|string $userId): array|null
  {
    $stmt = $this->db->prepare(
        "SELECT * FROM ".$this->table." WHERE (id = ? OR email = ?) LIMIT 1"
    );
    $stmt->bind_param("is", $userId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }
/**
 * Check login credentials
 * @access public
 * @param string|int $userId
 * @param string $pass
 * @return array|null
 */
public function login(string|int $userId, string $pass): array|null
  {
    $user = $this->get($userId);
    if (!$user)
      {
        return null;
      }
    if (!password_verify($pass, $user['pass']))
      {
        return null;
      }
    return $user;
  }
/** Remember the last successfully selected character for this user. */
public function rememberModel(int $userId, int $modelId): bool
  {
    $stmt = $this->db->prepare(
        "UPDATE users SET last_model_id = ? WHERE id = ?"
    );
    $stmt->bind_param('ii', $modelId, $userId);
    return $stmt->execute();
  }

}
?>