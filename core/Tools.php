<?php
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        Tools.php
** Class:       Tools
** Description: Load and provide installed tools
** Version:     2.0
** Updated:     2026-09-30
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/

use mara\core\tools\ToolInterface;
use mara\core\tools\ToolRegistry;

class Tools
{
  private ToolRegistry $registry;

/**
 * Construct
 *
 * @access public
 */
public function __construct()
  {
    $this->registry = new ToolRegistry();

    $this->loadTools();
  }

/**
 * Load every installed tool.
 *
 * @access private
 * @return void
 */
private function loadTools(): void
  {
    $files = glob(__DIR__.'/tools/*.tool.php');

    if ($files === false)
      {
        return;
      }

    foreach ($files as $file)
      {
        $tool = require $file;

        if (!$tool instanceof ToolInterface)
          {
            continue;
          }

        $this->registry->register($tool);
      }
  }

/**
 * Get tool registry.
 *
 * @access public
 * @return ToolRegistry
 */
public function registry(): ToolRegistry
  {
    return $this->registry;
  }

/**
 * Get definitions for the selected installed tools.
 *
 * @access public
 * @param array $names
 * @return array
 */
public function get(array $names): array
  {
    return $this->registry->definitions($names);
  }
}
?>
