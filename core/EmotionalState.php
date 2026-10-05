<?php
declare(strict_types=1);
namespace mara\core;

/** Eight independent character-emotion intensities; not percentages. */
final class EmotionalState
{
    public const AXES = ['joy', 'trust', 'fear', 'surprise', 'sadness', 'disgust', 'anger', 'anticipation'];

    public static function neutral(): array
    {
        return array_fill_keys(self::AXES, 0);
    }

    /** Keep emotion scores strict; shorten only overlong explanatory notes. */
    public static function validate(array $args): array
    {
        $expected = array_merge(self::AXES, ['note']);
        if (array_diff(array_keys($args), $expected)) {
            throw new \InvalidArgumentException('Unexpected emotional-state field.');
        }
        $state = [];
        foreach (self::AXES as $key) {
            if (!array_key_exists($key, $args) || !is_int($args[$key]) || $args[$key] < 0 || $args[$key] > 10) {
                throw new \InvalidArgumentException($key.' must be an integer from 0 to 10.');
            }
            $state[$key] = $args[$key];
        }
        if (!isset($args['note']) || !is_string($args['note'])) {
            throw new \InvalidArgumentException('A short emotional-state note is required.');
        }
        $note = trim($args['note']);
        if ($note === '') {
            throw new \InvalidArgumentException('A non-empty emotional-state note is required.');
        }
        if (!preg_match('//u', $note)) {
            throw new \InvalidArgumentException('The note must be valid UTF-8.');
        }
        // Explicit UTF-8 keeps accented letters intact regardless of mb_internal_encoding.
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            $length = mb_strlen($note, 'UTF-8');
            if ($length > 240) {
                $note = mb_substr($note, 0, 240, 'UTF-8');
            }
        } else {
            // Preserve Unicode characters even without the mbstring extension.
            $characters = preg_split('//u', $note, -1, PREG_SPLIT_NO_EMPTY);
            $length = count($characters);
            if ($length > 240) {
                $note = implode('', array_slice($characters, 0, 240));
            }
        }
        if ($length > 240) {
            self::log(['status' => 'NOTE_TRUNCATED',
                'original_length' => $length, 'saved_length' => 240]);
        }
        return ['state' => $state, 'note' => $note];
    }

    public static function prompt(array $state): string
    {
        return "[Current character emotional state, 0-10]\n".json_encode($state, JSON_THROW_ON_ERROR);
    }

    public static function log(array $entry): void
    {
        $path = dirname(__DIR__).'/var/log/emotional-ball.log';
        $line = json_encode(['time' => gmdate('c')] + $entry,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        if (@file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('Emotional Ball: cannot write '.$path);
        }
    }
}
