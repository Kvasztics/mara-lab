<?php
declare(strict_types=1);
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        ToolRunner.php
** Class:       ToolRunner
** Description: Tool functions
** Version:     1.1
** Updated:     2026-09-28
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use mara\core\App;
use mara\core\Tools;
use mara\core\tools\ToolRegistry;


class ToolRunner
{
  private ToolRegistry $registry;
/**
 * Constructor
 *
 * @access public
 * @param ToolRegistry $registry
 */
public function __construct(ToolRegistry $registry)
  {
    $this->registry = $registry;
  }
  /**
   * Execute tool
   *
   * @param string $name
   * @param array $args
   * @param array $context
   * @return array
   */
  public function run(
      string $name,
      array $args = [],
      array $context = []
  ): array
    {
      $started = microtime(true);

      $this->log('START', $name, ['args' => $args, 'context' => $context]);
      try
        {
          $tool = $this->registry->get($name);
          if ($tool === null) {
              throw new \RuntimeException('Unknown tool: '.$name);
          }
          Status::set($tool->status());
          $result = $tool->execute(
              $args,
              $context
          );

          $this->log(
              'OK',
              $name,
              [
                'duration' => microtime(true) - $started,
                'result'   => $result
              ]
          );

          return [
            'success' => true,
            'tool'    => $name,
            'result'  => $result,
            'error'   => null
          ];
        }
      catch (\Throwable $e)
        {
          $this->log(
              'ERROR',
              $name,
              [
                'duration' => microtime(true) - $started,
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine()
              ]
          );

          /*
           * A tool hibája soha nem szakíthatja meg
           * a normál chat folyamatát.
           */
          return [
            'success' => false,
            'tool'    => $name,
            'result'  => null,
            'error'   => $e->getMessage()
          ];
        }
    }
/**
 * Get tool definitions
 *
 * @access public
 * @param array $names
 * @return array
 */
public function definitions(array $names): array
  {
    return $this->registry->definitions($names);
  }  
  /**
   * Write tool log
   */
  public function log(
      string $status,
      string $tool,
      array $data = []
  ): void
  {
    $entry = [
      'time'   => date('Y-m-d H:i:s'),
      'status' => $status,
      'tool'   => $tool,
      'data'   => $data
    ];

    // Logging must never interrupt a chat, even when the directory is unwritable.
    try {
        $directory = dirname(__DIR__).'/var/log';
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            error_log('Mara tool log: cannot create log directory');
            return;
        }
        $json = json_encode(self::sanitize($entry), JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (@file_put_contents($directory.'/tools.log', $json.PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log('Mara tool log: cannot write tools.log');
        }
    } catch (\Throwable $e) {
        error_log('Mara tool log: logging failed');
    }
  }

  private static function sanitize(mixed $value, string $key = '', int $depth = 0): mixed
  {
    if (preg_match('/password|passwd|secret|token|api[_-]?key|authorization|cookie|base64|image/i', $key)) {
        return '[REDACTED]';
    }
    if ($depth > 8) return '[DEPTH LIMIT]';
    if (is_array($value)) {
        $safe = [];
        foreach (array_slice($value, 0, 100, true) as $k => $v) {
            $safe[$k] = self::sanitize($v, (string)$k, $depth + 1);
        }
        return $safe;
    }
    if (is_string($value)) {
        $value = preg_replace('/data:[^\s]+;base64,[A-Za-z0-9+\/=]+/i', '[IMAGE OMITTED]', $value) ?? '';
        $value = preg_replace('/([?&](?:token|key|api_key|password)=)[^&\s]+/i', '$1[REDACTED]', $value) ?? '';
        return function_exists('mb_substr') ? mb_substr($value, 0, 4000, 'UTF-8') : substr($value, 0, 4000);
    }
    return is_scalar($value) || $value === null ? $value : '[OBJECT]';
  }
}
