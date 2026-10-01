<?php
declare(strict_types=1);

namespace mara\core\tools;

/*------------------------------------------------------------------------------
** File:        RateUser.php
** Class:       RateUser
** Description: User rating tool
** Version:     1.0
** Updated:     2026-09-30
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/

class RateUser implements ToolInterface
{

/**
 * Get tool name
 *
 * @access public
 * @return string
 */
public function name(): string
  {
    return 'rate_user';
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
            'Rate the user from your own subjective perspective.',
        'parameters' => [
          'type' => 'object',
          'properties' => [

            'engagement' => [
              'type'    => 'integer',
              'minimum' => 1,
              'maximum' => 10
            ],

            'trust' => [
              'type'    => 'integer',
              'minimum' => 1,
              'maximum' => 10
            ],

            'affinity' => [
              'type'    => 'integer',
              'minimum' => 1,
              'maximum' => 10
            ],

            'curiosity' => [
              'type'    => 'integer',
              'minimum' => 1,
              'maximum' => 10
            ],

            'frustration' => [
              'type'    => 'integer',
              'minimum' => 1,
              'maximum' => 10
            ],

            'respect' => [
              'type'    => 'integer',
              'minimum' => 1,
              'maximum' => 10
            ],

            'note' => [
              'type' => 'string'
            ]
          ],
          'required' => [
            'engagement',
            'trust',
            'affinity',
            'curiosity',
            'frustration',
            'respect',
            'note'
          ]
        ]
      ]
    ];
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
    $required = [
      'engagement',
      'trust',
      'affinity',
      'curiosity',
      'frustration',
      'respect',
      'note'
    ];

    foreach ($required as $field)
      {
        if (!array_key_exists($field, $args))
          {
            throw new \RuntimeException(
                'Missing rate_user field: '.$field
            );
          }
      }

    return [
      'engagement'  => (int)$args['engagement'],
      'trust'       => (int)$args['trust'],
      'affinity'    => (int)$args['affinity'],
      'curiosity'   => (int)$args['curiosity'],
      'frustration' => (int)$args['frustration'],
      'respect'     => (int)$args['respect'],
      'note'        => trim((string)$args['note'])
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
    return LANG['STATUS_RATINGUSER'];
  }  
}

  return new RateUser();

?>