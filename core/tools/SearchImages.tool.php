<?php
declare(strict_types=1);

namespace mara\core\tools;

final class SearchImages implements ToolInterface
{
    public function name(): string
    {
        return 'search_images';
    }

    public function status(): string
    {
        return LANG['STATUS_SEARCHIMAGES'];
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' =>
                    'Search the internet for existing images. '
                    . 'Returns image URLs and the webpages where they were found. '
                    . 'Use this tool when the user asks to find or show existing images from the internet. '
                    . 'Preserve exact names, locations and important keywords. '
                    . 'Only present image URLs returned by this tool, and include their source page links when available. '
                    . 'Never invent, reconstruct or modify image URLs. '
                    . 'Search results are not proof that an image was inspected or that reuse is permitted. '
                    . 'If no results are returned, say that no usable image links were found.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'A concise image search query preserving the user request.'
                        ],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private static function validUrl(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)
            ? $value : '';
    }

    public function execute(array $args, array $context = []): mixed
    {
        $query = $args['query'] ?? null;
        if (!is_string($query) || trim($query) === '') {
            throw new \RuntimeException('Missing image search query.');
        }

        $config = require __DIR__ . '/SearchWeb.config.php';
        $endpoint = self::validUrl($config['endpoint'] ?? null);
        if ($endpoint === '') {
            throw new \RuntimeException('Image search endpoint is not configured.');
        }

        $limit = max(1, min(20, (int)($config['limit'] ?? 5)));
        $url = $endpoint . (str_contains($endpoint, '?') ? '&' : '?')
            . http_build_query([
                'q' => trim($query),
                'format' => 'json',
                'categories' => 'images',
            ]);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Cannot initialize image search.');
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => max(1, (int)($config['timeout'] ?? 20)),
                CURLOPT_CONNECTTIMEOUT => max(1, (int)($config['connect_timeout'] ?? 5)),
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $response = curl_exec($ch);
            if ($response === false) {
                throw new \RuntimeException('Image search error: ' . curl_error($ch));
            }
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code !== 200) {
                throw new \RuntimeException('Image search HTTP error: ' . $code);
            }
        } finally {
            curl_close($ch);
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_array($data['results'] ?? null)) {
            throw new \RuntimeException('Invalid image search response.');
        }

        $results = [];
        $seen = [];
        foreach ($data['results'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $engine = is_string($item['engine'] ?? null)
                ? strtolower(trim($item['engine'])) : '';
            if (in_array($engine, ['devicons', 'lucide'], true)) {
                continue;
            }
            $imageUrl = self::validUrl($item['img_src'] ?? null);
            if ($imageUrl === '' || isset($seen[$imageUrl])) {
                continue;
            }
            $seen[$imageUrl] = true;
            $results[] = [
                'title' => is_string($item['title'] ?? null) ? $item['title'] : '',
                'page_url' => self::validUrl($item['url'] ?? null),
                'image_url' => $imageUrl,
                'thumbnail' => self::validUrl($item['thumbnail_src'] ?? $item['thumbnail'] ?? null),
                'source' => is_string($item['source'] ?? null) ? $item['source'] : '',
                'engine' => is_string($item['engine'] ?? null) ? $item['engine'] : '',
            ];
            if (count($results) >= $limit) {
                break;
            }
        }
        return $results;
    }
}

return new SearchImages();
