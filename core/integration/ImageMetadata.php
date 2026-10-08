<?php
declare(strict_types=1);

namespace mara\core\integration;

final class ImageMetadata
{
    private const LIMIT = 1048576;

    public static function read(string $path): array
    {
        $parameters = '';
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open image metadata.');
        }

        try {
            $size = fstat($handle)['size'] ?? 0;
            if (fread($handle, 8) !== "\x89PNG\r\n\x1a\n") {
                return self::parse('');
            }

            for ($count = 0; $count < 100000; $count++) {
                $header = fread($handle, 8);
                if (strlen($header) !== 8) {
                    break;
                }
                $length = unpack('N', substr($header, 0, 4))[1];
                $type = substr($header, 4, 4);
                if ($length + 4 > $size - ftell($handle)) {
                    break;
                }
                if ($type === 'IEND') {
                    break;
                }
                if (!in_array($type, ['tEXt', 'zTXt', 'iTXt'], true)
                    || $length > self::LIMIT) {
                    if (fseek($handle, $length + 4, SEEK_CUR) !== 0) {
                        break;
                    }
                    continue;
                }

                $data = $length > 0 ? fread($handle, $length) : '';
                $crc = fread($handle, 4);
                if (strlen($data) !== $length || strlen($crc) !== 4
                    || hash('crc32b', $type . $data, true) !== $crc) {
                    continue;
                }
                $separator = strpos($data, "\0");
                if ($separator === false
                    || substr($data, 0, $separator) !== 'parameters') {
                    continue;
                }

                $text = substr($data, $separator + 1);
                if ($type === 'zTXt') {
                    if ($text === '' || ord($text[0]) !== 0) {
                        continue;
                    }
                    $text = @gzuncompress(substr($text, 1), self::LIMIT);
                } elseif ($type === 'iTXt') {
                    if (strlen($text) < 2
                        || !in_array(ord($text[0]), [0, 1], true)
                        || ord($text[1]) !== 0) {
                        continue;
                    }
                    $compressed = ord($text[0]) === 1;
                    $text = substr($text, 2);
                    // Skip language tag and translated keyword.
                    for ($i = 0; $i < 2; $i++) {
                        $end = strpos($text, "\0");
                        if ($end === false) {
                            continue 2;
                        }
                        $text = substr($text, $end + 1);
                    }
                    if ($compressed) {
                        $text = @gzuncompress($text, self::LIMIT);
                    }
                }

                if (is_string($text) && strlen($text) <= self::LIMIT) {
                    $parameters = mb_check_encoding($text, 'UTF-8')
                        ? $text : mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        return self::parse($parameters);
    }

    private static function parse(string $parameters): array
    {
        $positive = '';
        $negative = '';
        $settings = '';
        $parameters = str_replace(["\r\n", "\r"], "\n", $parameters);

        if (preg_match('/(?:^|\n)Steps:\s*\d+/', $parameters, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            $positive = trim(substr($parameters, 0, $offset));
            $settings = trim(substr($parameters, $offset));

            $marker = "\nNegative prompt:";
            $position = strpos($positive, $marker);
            if ($position !== false) {
                $negative = trim(substr($positive, $position + strlen($marker)));
                $positive = trim(substr($positive, 0, $position));
            }
        }

        // stable-diffusion.cpp also embeds structured generation data.
        $position = strpos($settings, 'SDCPP: ');
        if ($position !== false) {
            $structured = json_decode(substr($settings, $position + 7), true);
            if (is_array($structured) && is_array($structured['prompt'] ?? null)) {
                if (is_string($structured['prompt']['positive'] ?? null)) {
                    $positive = $structured['prompt']['positive'];
                }
                if (is_string($structured['prompt']['negative'] ?? null)) {
                    $negative = $structured['prompt']['negative'];
                }
                $settings = rtrim(substr($settings, 0, $position), " ,\n");
            }
        }

        return [
            'positive_prompt' => $positive,
            'negative_prompt' => $negative,
            'settings' => $settings,
            'parameters' => $parameters,
        ];
    }
}
