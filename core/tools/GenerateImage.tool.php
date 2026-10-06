<?php
declare(strict_types=1);

namespace mara\core\tools;

/*------------------------------------------------------------------------------
** File:        GenerateImage.tool.php
** Class:       GenerateImage
** Description: Image generation tool
** Version:     1.0
** Updated:     2026-09-30
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/

class GenerateImage implements ToolInterface
{

/**
 * Get tool name
 *
 * @access public
 * @return string
 */
public function name(): string
  {
    return 'generate_image';
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
            'Generate a new image using the image generation backend. '
            . 'If the user asks you to create, generate, draw, make, or produce an image, '
            . 'you MUST call this tool. '
            . 'The prompt passed to this tool MUST ALWAYS be written in English, '
            . 'regardless of the language used by the user. '
            . 'Translate the user request into a detailed English image-generation prompt before calling the tool. '
            . 'Never invent, guess, simulate, or write a generated image URL yourself. '
            . 'Only use the image URL returned by this tool.',
        'parameters' => [
          'type' => 'object',
          'properties' => [
            'prompt' => [
              'type' => 'string',
              'description' =>
                  'A detailed image-generation prompt written ONLY in English. '
                  . 'Never pass Hungarian or any other language in this parameter.'
            ]
          ],
          'required' => ['prompt']
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
    return LANG['STATUS_GENIMAGE'];
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
    $prompt = trim((string)($args['prompt'] ?? ''));

    if ($prompt === '') {
        throw new \RuntimeException('Missing image prompt.');
    }

    $config = require __DIR__ . '/GenerateImage.config.php';

    $backend = trim((string)\mara\core\App::get(
        'system.image_backend',
        ''
    ));

    $profile = $config['backends'][$backend] ?? null;

    if (!is_array($profile) || !is_array($profile['request'] ?? null)) {
        throw new \RuntimeException(
            'Image backend is not configured: ' . $backend
        );
    }

    $timeout = max(1, (int)($profile['timeout'] ?? 600));
    @set_time_limit($timeout + 30);

    $generator = new \mara\core\integration\ImageGenerator();

    return $generator->generate(
        $backend,
        $prompt,
        $profile['request'],
        (string)($config['save_path'] ?? ''),
        (string)($config['public_path'] ?? ''),
        $timeout
    );
  }

}

return new GenerateImage();
?>