<?php
declare(strict_types=1);

namespace mara\core\tools;

/*------------------------------------------------------------------------------
** File:        ToolInterface.php
** Interface:   ToolInterface
** Description: Common interface for executable Mara tools
** Version:     1.0
** Updated:     2026-09-30
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/

interface ToolInterface
{

/**
 * Get unique tool name
 *
 * @access public
 * @return string
 */
public function name(): string;


/**
 * Get provider tool definition
 *
 * @access public
 * @return array
 */
public function definition(): array;


/**
 * Execute tool
 *
 * @access public
 * @param array $args
 * @param array $context
 * @return mixed
 */
public function execute(
    array $args,
    array $context = []
): mixed;

/**
 * Get human readable tool status
 *
 * @access public
 * @return string
 */
public function status(): string;
}
?>