<?php
declare(strict_types=1);
namespace mara\core;

use mara\database\Database;
use mara\core\App;
use mara\core\Template;

final class Settings
  {
    private string $table = 'settings';
    private \mysqli $DB;
/**
 * Construct
 * @access public
 * @return void
 */
    public function __construct()
      {
        $this->DB = Database::getInstance()->getConnection();
      }
/**
 * Get values by group
 * @access public
 * @param string $group
 * @return array
 */
    public function values(string $group): array
      {
        $stmt = $this->DB->prepare(
            "SELECT datakey, datavalue
             FROM {$this->table}
             WHERE type = ?"
        );
        $stmt->bind_param('s', $group);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) 
          {
            $data[$row['datakey']] = $row['datavalue'];
          }
        return $data;
      }
/**
 * Get value by key
 * @access public
 * @param string $group
 * @param string $key
 * @return string
 */
    public function getValue(string $group, string $key): ?string
      {
        $stmt = $this->DB->prepare(
            "SELECT datavalue
             FROM {$this->table}
             WHERE type = ? AND datakey = ?
             LIMIT 1"
        );
        $stmt->bind_param('ss', $group, $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row['datavalue'] ?? null;
      }
/**
 * Set one setting
 * @access public
 * @param string $group
 * @param string $key
 * @param string $value
 * @return bool
 */
    public function setValue(string $group, string $key, string $value): bool 
      {
        $stmt = $this->DB->prepare(
            "UPDATE {$this->table}
             SET datavalue = ?
             WHERE type = ? AND datakey = ?"
        );
        $stmt->bind_param('sss', $value, $group, $key);
        return $stmt->execute();
      }
    public function saveReaderValues(array $values): void
    {
        $this->DB->begin_transaction();
        try {
            $rows = $this->DB->query("SELECT datakey FROM settings WHERE type = 'reader' FOR UPDATE");
            $existing = array_column($rows->fetch_all(MYSQLI_ASSOC), 'datakey');
            foreach ($values as $key => $value) {
                if (in_array($key, $existing, true)) {
                    if (!$this->setValue('reader', $key, (string)$value)) {
                        throw new \RuntimeException('Could not update Reader setting.');
                    }
                } else {
                    $stmt = $this->DB->prepare("INSERT INTO settings (type, datakey, datavalue) VALUES ('reader', ?, ?)");
                    $value = (string)$value;
                    $stmt->bind_param('ss', $key, $value);
                    if (!$stmt->execute()) {
                        throw new \RuntimeException('Could not insert Reader setting.');
                    }
                    $stmt->close();
                }
            }
            $this->DB->commit();
        } catch (\Throwable $error) {
            $this->DB->rollback();
            throw $error;
        }
    }

/**
 * Render languages to select
 * @access public
 * @param string $selected
 * @return string
 */
    public function getLanguages(string $selected): string 
      {
        $html      = '';
        $languages = explode(",", App::get('system.languages', 'HU'));
        foreach ($languages as $language) 
          {
            if ($selected == $language) {$data['selected'] = ' selected';} else {$data['selected'] = '';} 
            $data['value'] = $language;
            $data['title'] = LANG[$language];
            $T    = new Template(DIR_TPL.'/option.tpl.php', $data);
            $html.= $T->fetch();
          }
        return $html;  
      }
/**
 * Render providers to select
 * @access public
 * @param string $selected
 * @return string
 */
    public function getProviders(string $selected): string 
      {
        $html      = '';
        $providers = explode(",", App::get('system.providers', 'ollama'));
        foreach ($providers as $provider) 
          {
            if ($selected == $provider) {$data['selected'] = ' selected';} else {$data['selected'] = '';} 
            $data['value'] = $provider;
            $data['title'] = $provider;
            $T    = new Template(DIR_TPL.'/option.tpl.php', $data);
            $html.= $T->fetch();
          }
        return $html;  
      } 
/**
 * Get all provider name
 * @access public
 * @return array
 */
    public function getProviderNames(): array
      {
        $providers = App::get('system.providers', '');
        if (!is_string($providers) || trim($providers) === '') 
          {
            return [];
          }
        return array_values(
            array_filter(
                array_map('trim', explode(',', $providers))
            )
        );
      }      
}
?>