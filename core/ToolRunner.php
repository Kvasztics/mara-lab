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

      $tool = $this->registry->get($name);

      if ($tool === null)
        {
          throw new \RuntimeException(
              'Unknown tool: '.$name
          );
        }

      Status::set(
          $tool->status()
      );

      $this->log(
          'START',
          $name,
          [
            'args'    => $args,
            'context' => $context
          ]
      );

      try
        {
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
  private function log(
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

    file_put_contents(
        DIR_ROOT.'/logs/tools.log',
        json_encode(
            $entry,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ).PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
  }
}
?>