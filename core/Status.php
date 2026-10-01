<?php
declare(strict_types=1);
namespace mara\core;

/**
 * Runtime status handler.
 *
 * Stores temporary status information separately
 * for each PHP session.
 */
final class Status
  {

  /**
   * Set current status.
   *
   * @param string $status
   * @return void
   */
  public static function set(string $status): void
    {
      file_put_contents(
          self::getFile(),
          $status,
          LOCK_EX
      );
    }

  /**
   * Get current status.
   *
   * @return string
   */
  public static function get(): string
    {
      $file = self::getFile();

      if (!is_file($file))
        {
          return '';
        }

      return file_get_contents($file) ?: '';
    }

  /**
   * Clear current status.
   *
   * @return void
   */
  public static function clear(): void
    {
      $file = self::getFile();

      if (is_file($file))
        {
          @unlink($file);
        }
    }

  /**
   * Get session specific status file.
   *
   * @return string
   */
  private static function getFile(): string
    {
      return sys_get_temp_dir()
          .'/mara_status_'
          .session_id();
    }

  }