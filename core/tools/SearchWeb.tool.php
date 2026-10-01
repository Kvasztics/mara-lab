<?php
declare(strict_types=1);

namespace mara\core\tools;

/*------------------------------------------------------------------------------
** File:        SearchWeb.tool.php
** Class:       SearchWeb
** Description: Web search tool
** Version:     1.0
** Updated:     2026-09-30
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/

class SearchWeb implements ToolInterface
{

/**
 * Get tool name
 *
 * @access public
 * @return string
 */
public function name(): string
  {
    return 'search_web';
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
            'Search the web and return search result titles, URLs and snippets. '
            . 'Use a short and precise search query that preserves the user\'s exact intent, '
            . 'names, locations and important keywords. '
            . 'Do not paraphrase names or transform words into different meanings. '
            . 'Search result snippets are not the contents of webpages. '
            . 'If information from a specific result is needed, use visit_webpage. '
            . 'When presenting search results to the user, ALWAYS include the exact URL '
            . 'returned by this tool for every result you mention. '
            . 'Never describe or recommend a search result without linking it. '
            . 'Never invent, reconstruct, shorten or modify a URL.',
        'parameters' => [
          'type' => 'object',
          'properties' => [
            'query' => [
              'type' => 'string',
              'description' =>
                  'A concise web search query using the user\'s original keywords, '
                  . 'including exact names and locations when provided.'
            ]
          ],
          'required' => ['query']
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
    return LANG['STATUS_SEARCHWEB'];
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
    $query = trim(
        (string)($args['query'] ?? '')
    );

    if ($query === '')
      {
        throw new \RuntimeException(
            'Missing search query.'
        );
      }

    $config = require __DIR__ . '/SearchWeb.config.php';

    $endpoint = (string)(
        $config['endpoint'] ?? ''
    );

    if ($endpoint === '')
      {
        throw new \RuntimeException(
            'Search endpoint is not configured.'
        );
      }

    $limit = max(
        1,
        (int)($config['limit'] ?? 5)
    );

    $url = $endpoint . '?' . http_build_query([
      'q'      => $query,
      'format' => 'json'
    ]);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT =>
          (int)($config['timeout'] ?? 20),
      CURLOPT_CONNECTTIMEOUT =>
          (int)($config['connect_timeout'] ?? 5)
    ]);

    $response = curl_exec($ch);

    if ($response === false)
      {
        $error = curl_error($ch);
        curl_close($ch);

        throw new \RuntimeException(
            'Web search error: '.$error
        );
      }

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($httpCode !== 200)
      {
        throw new \RuntimeException(
            'Web search HTTP error: '.$httpCode
        );
      }

    $data = json_decode(
        $response,
        true
    );

    if (!is_array($data))
      {
        throw new \RuntimeException(
            'Invalid web search response.'
        );
      }

    $results = [];

    foreach (
        array_slice(
            $data['results'] ?? [],
            0,
            $limit
        ) as $item
    )
      {
        $results[] = [
          'title'   => $item['title'] ?? '',
          'url'     => $item['url'] ?? '',
          'content' => $item['content'] ?? '',
          'engine'  => $item['engine'] ?? ''
        ];
      }

    return $results;
  }
}

return new SearchWeb();
?>