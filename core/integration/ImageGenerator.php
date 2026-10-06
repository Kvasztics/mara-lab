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
        string $mode = 'txt2img'
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
                throw new \RuntimeException(
                    'Image generation error: ' . curl_error($ch)
                );
            }

            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new \RuntimeException(
                    'Image generation HTTP error: ' . $httpCode
                );
            }
        } finally {
            curl_close($ch);
        }

        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $encodedImages = $mode === 'extra-single-image'
            ? (isset($result['image']) && is_string($result['image']) && $result['image'] !== ''
                ? [$result['image']] : [])
            : ($result['images'] ?? null);
        $cancelled = $job->wasCancelled();

        if (
            !is_array($encodedImages) ||
            !array_is_list($encodedImages) ||
            $encodedImages === []
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
        if ($backend === 'forge') {
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
                $first < count($encodedImages)
            ) {
                $encodedImages = array_slice($encodedImages, $first);
            }
        }

        $images = [];
        $savedFiles = [];

        try {
            foreach ($encodedImages as $encoded) {
                if (!is_string($encoded) || $encoded === '') {
                    throw new \RuntimeException('Invalid encoded image.');
                }

                if (str_starts_with($encoded, 'data:image/')) {
                    $comma = strpos($encoded, ',');
                    if ($comma === false) {
                        throw new \RuntimeException('Invalid image data URI.');
                    }
                    $encoded = substr($encoded, $comma + 1);
                }

                $imageData = base64_decode($encoded, true);
                if ($imageData === false || $imageData === '') {
                    throw new \RuntimeException('Invalid generated image data.');
                }

                $imageInfo = @getimagesizefromstring($imageData);
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
}
