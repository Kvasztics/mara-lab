<?php
declare(strict_types=1);

namespace mara\core\integration;

final class ImageInput
{
    public static function normalize(string $encoded): string
    {
        if (strlen($encoded) > 14 * 1024 * 1024) {
            throw new \InvalidArgumentException('Image input too large.', 413);
        }

        if (str_starts_with($encoded, 'data:')) {
            if (!preg_match(
                '~^data:image/(png|jpeg|webp);base64,(.*)$~sD',
                $encoded,
                $match
            )) {
                throw new \InvalidArgumentException('Invalid image data URI.', 422);
            }
            $encoded = $match[2];
        }

        $bytes = base64_decode($encoded, true);
        if ($bytes === false || $bytes === '') {
            throw new \InvalidArgumentException('Invalid image data.', 422);
        }
        if (strlen($bytes) > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('Image input too large.', 413);
        }

        $info = @getimagesizefromstring($bytes);
        if (
            $info === false ||
            !in_array($info['mime'] ?? '', [
                'image/png', 'image/jpeg', 'image/webp',
            ], true) ||
            $info[0] <= 0 ||
            $info[1] <= 0
        ) {
            throw new \InvalidArgumentException('Unsupported image input.', 422);
        }

        if (
            $info[0] > 8192 ||
            $info[1] > 8192 ||
            $info[0] * $info[1] > 32000000
        ) {
            throw new \InvalidArgumentException('Image dimensions too large.', 413);
        }

        return base64_encode($bytes);
    }
}
