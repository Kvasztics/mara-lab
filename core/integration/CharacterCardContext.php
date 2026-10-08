<?php
declare(strict_types=1);

namespace mara\core\integration;

use mara\core\User;

final class CharacterCardContext
{
    public static function card(array $model): ?\stdClass
    {
        $json = $model['card_data'] ?? null;
        if (!is_string($json) || $json === '') {
            return null;
        }
        try {
            return CharacterCard::decode($json);
        } catch (\Throwable $error) {
            error_log('Mara character context: ' . $error->getMessage());
            return null;
        }
    }

    public static function names(string $text, array $model): string
    {
        return strtr($text, [
            '{{user}}' => User::name(),
            '{{char}}' => (string)($model['name'] ?? ''),
            '{{assistant}}' => (string)($model['name'] ?? ''),
        ]);
    }

    public static function withoutLegacyExamples(string $text, string $examples): string
    {
        $examples = trim($examples);
        if ($examples === '') {
            return $text;
        }
        $suffix = "\n\nExample dialogue:\n" . $examples;
        return str_ends_with($text, $suffix)
            ? substr($text, 0, -strlen($suffix))
            : $text;
    }

    public static function profilePrompt(array $model): string
    {
        $prompt = (string)($model['psyche_data']['prompt'] ?? '');
        $card = self::card($model);
        return $card === null ? $prompt
            : self::withoutLegacyExamples($prompt, $card->data->mes_example);
    }

    public static function systemPrompt(array $model): string
    {
        $prompt = trim((string)($model['prompt'] ?? ''));
        if (!empty($model['psyche'])) {
            $profile = trim(self::profilePrompt($model));
            if ($profile !== '') {
                $prompt .= ($prompt !== '' ? "\n\n" : '') . $profile;
            }
        }
        return $prompt;
    }

    public static function runtimeSystem(string $storedPrompt, array $model): string
    {
        $card = self::card($model);
        $examples = $card === null ? '' : trim($card->data->mes_example);
        $prompt = self::withoutLegacyExamples($storedPrompt, $examples);
        $prompt = self::names($prompt, $model);

        if ($examples !== '') {
            $prompt .= "\n\nExample dialogue:\n"
                . "The following is illustrative dialogue showing character style. "
                . "It is not actual conversation history or a memory of this user.\n"
                . self::names($examples, $model);
        }
        return $prompt;
    }

    public static function greeting(array $model): string
    {
        $card = self::card($model);
        return $card === null ? ''
            : self::names(trim($card->data->first_mes), $model);
    }
}
