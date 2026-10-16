<?php
declare(strict_types=1);
namespace mara\core;

/** Bounded, read-only access to explicitly registered application logs. */
final class LogViewer
{
    public static function sources(): array
    {
        return [
            'communication' => ['label' => 'Modellkommunikáció', 'path' => dirname(__DIR__).'/var/log/model-communication.log'],
            'ollama' => ['label' => 'Ollama'],
            'llamacpp' => ['label' => 'llama.cpp', 'path' => (string)App::get('llamacpp.log_file', '/tmp/mara-llama.log')],
            'emotional' => ['label' => 'Emotional Ball', 'path' => dirname(__DIR__).'/var/log/emotional-ball.log'],
            'tools' => ['label' => 'Toolok', 'path' => dirname(__DIR__).'/var/log/tools.log'],
            'nginx' => ['label' => 'Nginx hibák', 'path' => '/var/log/nginx/error.log'],
        ];
    }

    public static function read(string $source): array
    {
        $sources = self::sources();
        if (!isset($sources[$source])) {
            throw new \RuntimeException('Ismeretlen napló.');
        }
        if ($source === 'ollama') {
            return self::readOllama();
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

    /** Read only Ollama's journal through the restricted service helper. */
    private static function readOllama(): array
    {
        $output = tmpfile();
        $errors = tmpfile();
        if ($output === false || $errors === false) {
            if (is_resource($output)) fclose($output);
            if (is_resource($errors)) fclose($errors);
            throw new \RuntimeException('Nem készíthető ideiglenes napló.');
        }

        try {
            $process = proc_open(
                [
                    '/usr/bin/timeout', '8s',
                    '/usr/bin/sudo', '-n',
                    '/usr/local/bin/mara-service', 'logs', 'ollama'
                ],
                [
                    0 => ['file', '/dev/null', 'r'],
                    1 => $output,
                    2 => $errors,
                ],
                $pipes
            );

            if (!is_resource($process)) {
                throw new \RuntimeException('Az Ollama naplólekérése nem indítható.');
            }

            $code = proc_close($process);
            if ($code !== 0) {
                throw new \RuntimeException(
                    'Az Ollama naplója nem kérhető le. Ellenőrizd a mara-service helper telepítését és jogosultságait.'
                );
            }

            $stat = fstat($output);
            if ($stat === false) {
                throw new \RuntimeException('Az Ollama naplója nem olvasható.');
            }

            $offset = max(0, (int)$stat['size'] - 262144);
            if (fseek($output, $offset) !== 0) {
                throw new \RuntimeException('Az Ollama naplója nem olvasható.');
            }
            $text = stream_get_contents($output, 262144);
            if ($text === false) {
                throw new \RuntimeException('Az Ollama naplója nem olvasható.');
            }
            if ($offset > 0) {
                $newline = strpos($text, "\n");
                $text = $newline === false ? '' : substr($text, $newline + 1);
            }
            $lines = $text === '' ? [] : explode("\n", rtrim($text, "\r\n"));
            $limited = $offset > 0 || count($lines) >= 200;
            $lines = array_slice($lines, -200);

            return [
                'source' => 'ollama',
                'text' => implode("\n", $lines),
                'lines' => count($lines),
                'limited' => $limited,
                'updated' => gmdate('c'),
            ];
        } finally {
            fclose($output);
            fclose($errors);
        }
    }
}
