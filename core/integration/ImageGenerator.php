<?php
declare(strict_types=1);

namespace mara\core\integration;

use mara\core\App;

final class ImageGenerator
{
    public function generate(
        string $backend,
        string $prompt,
        array $request,
        string $savePath,
        string $publicPath,
        int $timeout = 600,
        int $userId = 0,
        string $jobId = '',
        string $mode = 'txt2img',
        string $fallbackUrlTemplate = ''
    ): array {
        if (!in_array($backend, ['forge', 'qwen2'], true)) {
            throw new \RuntimeException('Unknown image backend.');
        }

        if (
            !in_array($mode, ['txt2img', 'img2img', 'extra-single-image'], true) ||
            ($mode !== 'txt2img' && $backend !== 'forge')
        ) {
            throw new \RuntimeException('Unsupported image generation mode.');
        }

        $prompt = trim($prompt);
        if ($prompt === '' && $mode !== 'extra-single-image') {
            throw new \RuntimeException('Missing image prompt.');
        }

        $url = rtrim(trim(
            (string) App::get('system.' . $backend . '_url', '')
        ), '/');

        if (
            filter_var($url, FILTER_VALIDATE_URL) === false ||
            !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            throw new \RuntimeException('Invalid image backend URL.');
        }

        $savePath = rtrim($savePath, '/');
        $publicPath = '/' . trim($publicPath, '/');

        if ($savePath === '' || $publicPath === '/') {
            throw new \RuntimeException('Image storage is not configured.');
        }

        if (
            !is_dir($savePath) &&
            !mkdir($savePath, 0775, true) &&
            !is_dir($savePath)
        ) {
            throw new \RuntimeException('Cannot create image storage directory.');
        }

        if (!is_writable($savePath)) {
            throw new \RuntimeException('Image storage directory is not writable.');
        }

        $job = new ImageJob(
            $url,
            $userId,
            $jobId !== '' ? $jobId : bin2hex(random_bytes(16))
        );

        if ($mode !== 'extra-single-image') {
            $request['prompt'] = $prompt;
        }
        $payload = json_encode($request, JSON_THROW_ON_ERROR);
        $timeout = max(1, $timeout);

        $ch = curl_init($url . '/sdapi/v1/' . $mode);
        if ($ch === false) {
            throw new \RuntimeException('Cannot initialize image request.');
        }

        $externalImage = null;
        try {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);

            $response = curl_exec($ch);

            if ($response === false) {
                if ($mode !== 'txt2img' || $fallbackUrlTemplate === '' ||
                    !in_array(curl_errno($ch), [CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST], true) ||
                    $job->wasCancelled()) {
                    throw new \RuntimeException('Image generation error: ' . curl_error($ch));
                }
                $externalImage = $this->fetchExternalImage($fallbackUrlTemplate, $prompt);
            } else {
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode < 200 || $httpCode >= 300) {
                    throw new \RuntimeException('Image generation HTTP error: ' . $httpCode);
                }
            }
        } finally {
            curl_close($ch);
        }

        if ($externalImage !== null) {
            $imageValues = [$externalImage];
        } else {
            $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            $imageValues = $mode === 'extra-single-image'
                ? (isset($result['image']) && is_string($result['image']) && $result['image'] !== ''
                    ? [$result['image']] : [])
                : ($result['images'] ?? null);
        }
        $cancelled = $job->wasCancelled();

        if (
            !is_array($imageValues) ||
            !array_is_list($imageValues) ||
            $imageValues === []
        ) {
            if ($cancelled) {
                return [
                    'prompt' => $prompt,
                    'image' => '',
                    'images' => [],
                    'cancelled' => true,
                ];
            }
            throw new \RuntimeException('Backend returned no images.');
        }

        // Forge can prepend a composite grid before the individual images.
        if ($backend === 'forge' && $externalImage === null) {
            $info = $result['info'] ?? [];
            if (is_string($info)) {
                $info = json_decode($info, true);
            }
            $first = is_array($info)
                ? ($info['index_of_first_image'] ?? 0)
                : 0;
            if (
                is_int($first) &&
                $first > 0 &&
                    $first < count($imageValues)
            ) {
                    $imageValues = array_slice($imageValues, $first);
            }
        }

        $images = [];
        $savedFiles = [];

        try {
            foreach ($imageValues as $value) {
                if (!is_string($value) || $value === '') {
                    throw new \RuntimeException('Invalid encoded image.');
                }

                if ($externalImage === null && str_starts_with($value, 'data:image/')) {
                    $comma = strpos($value, ',');
                    if ($comma === false) {
                        throw new \RuntimeException('Invalid image data URI.');
                    }
                    $value = substr($value, $comma + 1);
                }

                $imageData = $externalImage === null ? base64_decode($value, true) : $value;
                if ($imageData === false || $imageData === '') {
                    throw new \RuntimeException('Invalid generated image data.');
                }

                $imageInfo = @getimagesizefromstring($imageData);
                if ($externalImage !== null && (
                    ($imageInfo[0] ?? 0) !== 512 || ($imageInfo[1] ?? 0) !== 512 ||
                    !in_array($imageInfo['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)
                )) {
                    throw new \RuntimeException(LANG['IMAGE_EXTERNAL_INVALID']);
                }
                $extension = match ($imageInfo['mime'] ?? '') {
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/webp' => 'webp',
                    default => throw new \RuntimeException('Unsupported image format.'),
                };

                $filename = 'gen_' . date('Ymd_His') . '_'
                    . bin2hex(random_bytes(8)) . '.' . $extension;
                $filePath = $savePath . '/' . $filename;

                $written = file_put_contents($filePath, $imageData, LOCK_EX);
                if ($written !== strlen($imageData)) {
                    @unlink($filePath);
                    throw new \RuntimeException('Generated image cannot be saved.');
                }

                $savedFiles[] = $filePath;
                $images[] = rtrim(DIR_HOST, '/') . $publicPath . '/' . $filename;
            }
        } catch (\Throwable $error) {
            foreach ($savedFiles as $filePath) {
                @unlink($filePath);
            }
            throw $error;
        }

        return [
            'prompt' => $prompt,
            'image' => $images[0],
            'images' => $images,
            'cancelled' => $cancelled,
        ];
    }

    private function fetchExternalImage(string $template, string $prompt): string
    {
        // Substitute only in the path or query, keeping the configured host fixed.
        $parts = parse_url($template);
        $promptLocation = ($parts['path'] ?? '') . '?' . ($parts['query'] ?? '');
        $url = str_replace('{prompt}', rawurlencode($prompt), $template);
        if (substr_count($template, '{prompt}') !== 1 ||
            !str_contains($promptLocation, '{prompt}') ||
            ($parts['scheme'] ?? '') !== 'https' ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) ||
            filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException(LANG['IMAGE_EXTERNAL_TEMPLATE']);
        }

        $body = '';
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException(LANG['IMAGE_EXTERNAL_REQUEST']);
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 90,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => ['Accept: image/png, image/jpeg, image/webp'],
                CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
                    $length = strlen($chunk);
                    if (strlen($body) + $length > 8 * 1024 * 1024) return 0;
                    $body .= $chunk;
                    return $length;
                },
            ]);
            $ok = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($ok === false || $status < 200 || $status >= 300 || $body === '') {
                throw new \RuntimeException(LANG['IMAGE_EXTERNAL_REQUEST']);
            }
            return $body;
        } finally {
            curl_close($ch);
        }
    }
}
