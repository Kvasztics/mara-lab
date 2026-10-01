<?php

declare(strict_types=1);

namespace mara\core\voice;

/**
 * Produces speech-friendly text from a Markdown model response.
 */
final class VoiceText
{
    public static function clean(string $text): string
    {
        /* Code has no useful spoken form. Remove complete fenced blocks first. */
        $text = preg_replace('/```[\s\S]*?```/u', ' ', $text) ?? $text;

        /* Keep the human label of a Markdown link, never read its URL aloud. */
        $text = preg_replace('/!?\[([^\]]*)\]\([^\)]*\)/u', '$1', $text) ?? $text;

        /* URLs and inline-code formatting are not useful to the listener. */
        $text = preg_replace('~https?://\S+~u', ' ', $text) ?? $text;
        $text = preg_replace('/`([^`]*)`/u', '$1', $text) ?? $text;

        $text = strip_tags($text);
        $text = preg_replace('/(^|\n)\s{0,3}[#>*]+\s*/u', '$1', $text) ?? $text;
        $text = str_replace(['**', '__', '~~', '_'], '', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
