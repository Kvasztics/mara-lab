<?php
declare(strict_types=1);

namespace mara\core\integration;

final class CharacterCardPng
{
    public const MAX_BYTES = 12582912;
    private const SIGNATURE = "\x89PNG\r\n\x1a\n";

    public static function read(string $png): \stdClass
    {
        $cards = [];
        foreach (self::chunks($png) as [$type, $data]) {
            if ($type !== 'tEXt') {
                continue;
            }
            $separator = strpos($data, "\0");
            if ($separator === false) {
                continue;
            }
            $key = substr($data, 0, $separator);
            if (!in_array($key, ['chara', 'ccv3'], true)) {
                continue;
            }
            if (isset($cards[$key])) {
                throw new \InvalidArgumentException('Duplicate character card chunk.');
            }
            $encoded = substr($data, $separator + 1);
            if (strlen($encoded) > 4 * (int)ceil(CharacterCard::MAX_JSON_BYTES / 3)) {
                throw new \InvalidArgumentException('Character card chunk is too large.');
            }
            $json = base64_decode($encoded, true);
            if ($json === false || $json === '') {
                throw new \InvalidArgumentException('Invalid character card encoding.');
            }
            $cards[$key] = $json;
        }

        $key = isset($cards['ccv3']) ? 'ccv3' : 'chara';
        if (!isset($cards[$key])) {
            throw new \InvalidArgumentException('PNG contains no character card.');
        }

        $card = CharacterCard::decode($cards[$key]);
        $expected = $key === 'ccv3' ? 'chara_card_v3' : 'chara_card_v2';
        if ($card->spec !== $expected) {
            throw new \InvalidArgumentException('Character card chunk version mismatch.');
        }
        return $card;
    }

    public static function portrait(string $png): string
    {
        $output = self::SIGNATURE;
        foreach (self::chunks($png) as [$type, $data]) {
            if (!in_array($type, ['tEXt', 'zTXt', 'iTXt'], true)) {
                $output .= self::chunk($type, $data);
            }
        }
        return $output;
    }

    public static function write(string $png, \stdClass $card): string
    {
        $json = CharacterCard::encode($card);
        $key = $card->spec === 'chara_card_v3' ? 'ccv3' : 'chara';
        $output = self::SIGNATURE;

        foreach (self::chunks($png) as [$type, $data]) {
            // Export only the portrait and card, without unrelated text metadata.
            if (in_array($type, ['tEXt', 'zTXt', 'iTXt'], true)) {
                continue;
            }
            if ($type === 'IEND') {
                $output .= self::chunk('tEXt', $key . "\0" . base64_encode($json));
            }
            $output .= self::chunk($type, $data);
        }

        if (strlen($output) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Exported character PNG is too large.');
        }
        return $output;
    }

    private static function chunks(string $png): array
    {
        $size = strlen($png);
        if ($size > self::MAX_BYTES ||
            substr($png, 0, 8) !== self::SIGNATURE) {
            throw new \InvalidArgumentException('Invalid character PNG.');
        }

        $offset = 8;
        $chunks = [];
        $hasImage = false;
        $hasEnd = false;

        while ($offset < $size) {
            if (count($chunks) >= 10000 || $size - $offset < 12) {
                throw new \InvalidArgumentException('Invalid PNG chunk structure.');
            }
            $length = unpack('Nlength', substr($png, $offset, 4))['length'];
            if ($length > $size - $offset - 12) {
                throw new \InvalidArgumentException('Truncated PNG chunk.');
            }

            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $length);
            $crc = substr($png, $offset + 8 + $length, 4);

            if (!preg_match('/^[A-Za-z]{4}$/D', $type) ||
                !hash_equals(hash('crc32b', $type . $data, true), $crc)) {
                throw new \InvalidArgumentException('Invalid PNG chunk checksum.');
            }

            if ($chunks === [] && ($type !== 'IHDR' || $length !== 13)) {
                throw new \InvalidArgumentException('PNG header is missing.');
            }
            if ($type === 'IHDR') {
                if ($chunks !== []) {
                    throw new \InvalidArgumentException('Duplicate PNG header.');
                }
                $dimensions = unpack('Nwidth/Nheight', substr($data, 0, 8));
                $width = $dimensions['width'];
                $height = $dimensions['height'];
                if ($width < 1 || $height < 1 ||
                    $width > 8192 || $height > 8192 ||
                    $width * $height > 32000000) {
                    throw new \InvalidArgumentException('PNG dimensions exceed the limit.');
                }
            }

            $chunks[] = [$type, $data];
            $offset += 12 + $length;
            $hasImage = $hasImage || $type === 'IDAT';

            if ($type === 'IEND') {
                if ($length !== 0 || $offset !== $size) {
                    throw new \InvalidArgumentException('Invalid PNG ending.');
                }
                $hasEnd = true;
                break;
            }
        }

        if (!$hasImage || !$hasEnd) {
            throw new \InvalidArgumentException('Incomplete character PNG.');
        }
        return $chunks;
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data
            . hash('crc32b', $type . $data, true);
    }
}
