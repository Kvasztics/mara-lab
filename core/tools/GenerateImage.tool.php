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
    $prompt = trim(
        (string)($args['prompt'] ?? '')
    );

    if ($prompt === '')
      {
        throw new \RuntimeException(
            'Missing image prompt.'
        );
      }

    $config = require __DIR__
        . '/GenerateImage.config.php';

    $endpoint = trim(
        (string)($config['endpoint'] ?? '')
    );

    if ($endpoint === '')
      {
        throw new \RuntimeException(
            'Image generation endpoint is not configured.'
        );
      }

    $request = $config['request'] ?? [];

    if (!is_array($request))
      {
        throw new \RuntimeException(
            'Invalid image generation request configuration.'
        );
      }

    /*
     * Prompt always comes from the tool call.
     */
    $request['prompt'] = $prompt;

    $ch = curl_init($endpoint);

    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => json_encode($request),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 300,
      CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json'
      ]
    ]);

    $response = curl_exec($ch);

    if ($response === false)
      {
        $error = curl_error($ch);
        curl_close($ch);

        throw new \RuntimeException(
            'Image generation error: '.$error
        );
      }

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    )
      {
        throw new \RuntimeException(
            'Image generation HTTP error: '
            . $httpCode
        );
      }

    $result = json_decode(
        $response,
        true
    );

    if (
        !is_array($result) ||
        empty($result['images'][0])
    )
      {
        throw new \RuntimeException(
            'Image generation backend returned no image.'
        );
      }

    $imageData = base64_decode(
        $result['images'][0],
        true
    );

    if ($imageData === false)
      {
        throw new \RuntimeException(
            'Invalid generated image data.'
        );
      }

    $savePath = rtrim(
        (string)($config['save_path'] ?? ''),
        '/'
    );

    if ($savePath === '')
      {
        throw new \RuntimeException(
            'Image save path is not configured.'
        );
      }

    /*
     * Create storage directory when necessary.
     */
    if (
        !is_dir($savePath) &&
        !mkdir($savePath, 0775, true)
    )
      {
        throw new \RuntimeException(
            'Image storage directory cannot be created.'
        );
      }

    $filename =
        'gen_'
        . time()
        . '_'
        . bin2hex(random_bytes(3))
        . '.png';

    $filenamePath =
        $savePath.'/'.$filename;

    if (
        file_put_contents(
            $filenamePath,
            $imageData
        ) === false
    )
      {
        throw new \RuntimeException(
            'Generated image cannot be saved.'
        );
      }

    $publicPath = '/'
        . trim(
            (string)($config['public_path'] ?? ''),
            '/'
        );

    return [
      'prompt' => $prompt,
      'image'  =>
          rtrim(DIR_HOST, '/')
          . $publicPath
          . '/'
          . $filename
          . '?t='
          . time()
    ];
  }
}

return new GenerateImage();
?>