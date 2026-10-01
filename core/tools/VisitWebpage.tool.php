<?php
declare(strict_types=1);

namespace mara\core\tools;

/*------------------------------------------------------------------------------
** File:        VisitWebpage.tool.php
** Class:       VisitWebpage
** Description: Web page reader tool
** Version:     1.0
** Updated:     2026-09-30
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/

class VisitWebpage implements ToolInterface
{

/**
 * Get tool name
 *
 * @access public
 * @return string
 */
public function name(): string
  {
    return 'visit_webpage';
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
            'Open and read the actual contents of a web page. '
            . 'Use this after web search when the contents of a specific result are needed. '
            . 'When you need this tool, call it using the native function calling mechanism. '
            . 'Do not write or simulate the tool call in your response. '
            . 'When answering based on the visited page, include the exact page URL '
            . 'in the final response. '
            . 'Never invent, reconstruct, shorten or modify the URL.',
        'parameters' => [
          'type' => 'object',
          'properties' => [
            'url' => [
              'type' => 'string',
              'description' =>
                  'The full HTTP or HTTPS URL to read.'
            ]
          ],
          'required' => ['url']
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
    return LANG['STATUS_VISITPAGE'];
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
    $url = trim(
        (string)($args['url'] ?? '')
    );

    if ($url === '')
      {
        throw new \RuntimeException(
            'Missing webpage URL.'
        );
      }

    /*
     * Only HTTP and HTTPS URLs are allowed.
     */
    $parts = parse_url($url);

    if (
        !$parts ||
        empty($parts['scheme']) ||
        !in_array(
            strtolower($parts['scheme']),
            ['http', 'https'],
            true
        )
    )
      {
        throw new \RuntimeException(
            'Invalid webpage URL.'
        );
      }

    $config = require __DIR__ . '/VisitWebpage.config.php';

    $ch = curl_init($url);

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS =>
          (int)($config['max_redirects'] ?? 5),
      CURLOPT_TIMEOUT =>
          (int)($config['timeout'] ?? 20),
      CURLOPT_CONNECTTIMEOUT =>
          (int)($config['connect_timeout'] ?? 5),
      CURLOPT_USERAGENT =>
          (string)($config['user_agent']
              ?? 'Mozilla/5.0 MaraWebReader/1.0')
    ]);

    $html = curl_exec($ch);

    if (
        $html === false ||
        trim($html) === ''
    )
      {
        $error = curl_error($ch);

        if ($error === '')
          {
            $error = 'Empty response.';
          }

        curl_close($ch);

        throw new \RuntimeException(
            'Webpage download error: '.$error
        );
      }

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $contentType = curl_getinfo(
        $ch,
        CURLINFO_CONTENT_TYPE
    );

    curl_close($ch);

    if (
        $httpCode < 200 ||
        $httpCode >= 400
    )
      {
        throw new \RuntimeException(
            'Webpage HTTP error: '.$httpCode
        );
      }

    if (
        $contentType &&
        stripos(
            $contentType,
            'text/html'
        ) === false
    )
      {
        throw new \RuntimeException(
            'Webpage content is not HTML: '
            . $contentType
        );
      }

    libxml_use_internal_errors(true);

    $dom = new \DOMDocument();

    $dom->loadHTML(
        $html,
        LIBXML_NOERROR |
        LIBXML_NOWARNING
    );

    libxml_clear_errors();

    /*
     * Remove content that is useless for the model.
     */
    $removeTags = [
      'script',
      'style',
      'noscript',
      'svg',
      'iframe'
    ];

    foreach ($removeTags as $tag)
      {
        while (true)
          {
            $nodes = $dom->getElementsByTagName(
                $tag
            );

            if ($nodes->length === 0)
              {
                break;
              }

            $node = $nodes->item(0);

            if ($node && $node->parentNode)
              {
                $node->parentNode->removeChild(
                    $node
                );
              }
          }
      }

    $title = '';

    $titles = $dom->getElementsByTagName(
        'title'
    );

    if ($titles->length > 0)
      {
        $title = trim(
            $titles->item(0)->textContent
        );
      }

    $text = $dom->textContent ?? '';

    /*
     * Normalize whitespace.
     */
    $text = preg_replace(
        '/[ \t]+/u',
        ' ',
        $text
    );

    $text = preg_replace(
        '/\R{2,}/u',
        "\n\n",
        $text
    );

    $text = trim($text);

    /*
     * Protect the context from huge webpages.
     */
    $maxLength = max(
        1000,
        (int)($config['max_length'] ?? 15000)
    );

    if (mb_strlen($text, 'UTF-8') > $maxLength)
      {
        $text = mb_substr(
            $text,
            0,
            $maxLength,
            'UTF-8'
        );

        $text .= "\n\n[CONTENT TRUNCATED]";
      }

    return [
      'url'     => $url,
      'title'   => $title,
      'content' => $text
    ];
  }
}

return new VisitWebpage();
?>