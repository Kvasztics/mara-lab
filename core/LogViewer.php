<?php
declare(strict_types=1);
namespace mara\core;

/** Bounded, read-only access to explicitly registered application logs. */
final class LogViewer
{
    public static function sources(): array
    {
        return [
            'llamacpp' => ['label' => 'llama.cpp', 'path' => (string)App::get('llamacpp.log_file', '/tmp/mara-llama.log')],
            'emotional' => ['label' => 'Emotional Ball', 'path' => dirname(__DIR__).'/var/log/emotional-ball.log'],
            'nginx' => ['label' => 'Nginx hibák', 'path' => '/var/log/nginx/error.log'],
        ];
    }

    public static function read(string $source): array
    {
        $sources = self::sources();
        if (!isset($sources[$source])) {
            throw new \RuntimeException('Ismeretlen napló.');
        }
        $path = $sources[$source]['path'];
        clearstatcache(true, $path);
        if ($path === '' || !is_file($path)) {
            throw new \RuntimeException('A naplófájl még nem létezik.');
        }
        if (!is_readable($path)) {
            throw new \RuntimeException('A webszervernek nincs olvasási joga ehhez a naplóhoz.');
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('A napló nem nyitható meg.');
        }
        try {
            $stat = fstat($handle);
            // Reject special files even if the configured path changes during open.
            if ($stat === false || ($stat['mode'] & 0170000) !== 0100000) {
                throw new \RuntimeException('Csak normál naplófájl olvasható.');
            }
            $size = (int)$stat['size'];
            $offset = max(0, $size - 262144);
            if (fseek($handle, $offset) !== 0) {
                throw new \RuntimeException('A napló nem olvasható.');
            }
            $text = stream_get_contents($handle, 262144);
            if ($text === false) {
                throw new \RuntimeException('A napló olvasása sikertelen.');
            }
            if ($offset > 0) {
                $newline = strpos($text, "\n");
                $text = $newline === false ? '' : substr($text, $newline + 1);
            }
            $lines = $text === '' ? [] : explode("\n", rtrim($text, "\r\n"));
            $limited = $offset > 0 || count($lines) > 200;
            $lines = array_slice($lines, -200);
            return ['source' => $source, 'text' => implode("\n", $lines),
                'lines' => count($lines), 'limited' => $limited,
                'updated' => gmdate('c')];
        } finally {
            fclose($handle);
        }
    }
}
