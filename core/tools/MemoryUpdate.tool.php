<?php
declare(strict_types=1);

namespace mara\core\tools;

use mara\database\mPsyche;

/*------------------------------------------------------------------------------
** File:        MemoryUpdate.tool.php
** Class:       MemoryUpdate
** Description: Persistent psyche memory update tool
** Version:     1.0
** Updated:     2026-09-30
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/

class MemoryUpdate implements ToolInterface
{

/**
 * Get tool name
 *
 * @access public
 * @return string
 */
public function name(): string
  {
    return 'update_memory';
  }


/**
 * Get provider tool definition
 *
 * @access public
 * @return array
 */
public function definition(): array
  {
    return [
      'type' => 'function',
      'function' => [
        'name' => $this->name(),
        'description' =>
            'Store a short, genuinely persistent memory for your current Psyché. '
            . 'Use this only for durable user preferences, important ongoing project facts, '
            . 'or stable relationship context that will help in future conversations. '
            . 'Do not store temporary moods, ordinary chat details, secrets, '
            . 'or information already in memory. '
            . 'By default the memory is appended. Use replace only when the user explicitly '
            . 'asks to replace or clear memory, or when you are deliberately consolidating it.',
        'parameters' => [
          'type' => 'object',
          'properties' => [
            'memory' => [
              'type' => 'string',
              'description' =>
                  'A concise, self-contained persistent memory. '
                  . 'Write only the information to remember, not a conversation summary.'
            ],
            'mode' => [
              'type' => 'string',
              'enum' => ['append', 'replace'],
              'description' =>
                  'append adds this memory to the existing memory and is the default. '
                  . 'replace discards the existing memory and must be used sparingly.'
            ],
            'reason' => [
              'type' => 'string',
              'description' =>
                  'A short internal reason why this information is durable and useful.'
            ]
          ],
          'required' => ['memory']
        ]
      ]
    ];
  }


/**
 * Get human readable tool status
 *
 * @access public
 * @return string
 */
public function status(): string
  {
    return LANG['STATUS_UPDATEMEMORY']
        ?? 'Memória frissítése...';
  }


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
): mixed
  {
    $modelId = (int)($context['model_id'] ?? 0);
    $memory = trim((string)($args['memory'] ?? ''));
    $mode = (string)($args['mode'] ?? 'append');
    $reason = trim((string)($args['reason'] ?? ''));

    if ($modelId <= 0)
      {
        throw new \RuntimeException(
            'Missing model context for memory update.'
        );
      }

    if ($memory === '')
      {
        throw new \RuntimeException(
            'Missing memory content.'
        );
      }

    if ($mode !== 'replace')
      {
        $mode = 'append';
      }

    $psyche = new mPsyche();

    if (!$psyche->updateLatestMemory(
        $modelId,
        $memory,
        $mode,
        $reason
    ))
      {
        throw new \RuntimeException(
            'The current model has no Psyché memory to update.'
        );
      }

    return [
      'message' => 'Persistent memory updated successfully.',
      'mode' => $mode,
      'characters_added' => function_exists('mb_strlen')
          ? mb_strlen($memory)
          : strlen($memory)
    ];
  }
}

return new MemoryUpdate();
?>
