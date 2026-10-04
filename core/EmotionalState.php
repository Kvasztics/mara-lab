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

    /** Validate tool input without silently clamping, truncating or inventing values. */
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
        $length = function_exists('mb_strlen') ? mb_strlen($note) : strlen($note);
        if ($note === '' || $length > 240) {
            throw new \InvalidArgumentException('The note must contain 1 to 240 characters.');
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
