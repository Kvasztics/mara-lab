<?php
declare(strict_types=1);

namespace mara\core\tools;

/*------------------------------------------------------------------------------
** File:        ToolRegistry.php
** Class:       ToolRegistry
** Description: Registry for executable Mara tools
** Version:     1.0
** Updated:     2026-09-30
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/

class ToolRegistry
{
  private array $tools = [];


/**
 * Register tool
 *
 * @access public
 * @param ToolInterface $tool
 * @return void
 */
public function register(ToolInterface $tool): void
  {
    $this->tools[$tool->name()] = $tool;
  }


/**
 * Get registered tool
 *
 * @access public
 * @param string $name
 * @return ToolInterface|null
 */
public function get(string $name): ?ToolInterface
  {
    return $this->tools[$name] ?? null;
  }


/**
 * Check registered tool
 *
 * @access public
 * @param string $name
 * @return bool
 */
public function has(string $name): bool
  {
    return isset($this->tools[$name]);
  }


/**
 * Get provider definitions for selected tools
 *
 * @access public
 * @param array $names
 * @return array
 */
public function definitions(array $names): array
  {
    $definitions = [];

    foreach ($names as $name)
      {
        $tool = $this->get((string)$name);

        if ($tool !== null)
          {
            $definitions[] = $tool->definition();
          }
      }

    return $definitions;
  }
}
?>