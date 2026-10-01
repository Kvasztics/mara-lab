<?php

declare(strict_types=1);

namespace mara\core\voice;

final class ESpeakProvider implements VoiceProviderInterface
{
    private string $binary;
    private string $voice;
    private int $pitch;
    private int $speed;

    public function __construct(array $config)
    {
        $this->binary = trim((string)($config['binary'] ?? ''));
        $this->voice = trim((string)($config['voice'] ?? ''));
        $this->pitch = (int)($config['pitch'] ?? 70);
        $this->speed = (int)($config['speed'] ?? 100);
    }

    public function isAvailable(): bool
    {
        return $this->binary !== '' && $this->voice !== '';
    }

    public function synthesize(array $voice, string $text): string
    {
        if (!$this->isAvailable() || trim($text) === '') {
            throw new \RuntimeException('eSpeak is not available.');
        }

        $parameters = (array)($voice['parameters'] ?? []);
        $voiceName = trim((string)($voice['sample'] ?? '')) ?: $this->voice;
        $pitch = (int)($parameters['pitch'] ?? $this->pitch);
        $speed = (int)($parameters['speed'] ?? $this->speed);

        $process = proc_open([
            $this->binary,
            '-v', $voiceName,
            '-p', (string)$pitch,
            '-s', (string)$speed,
            '--stdout',
        ], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Cannot start eSpeak.');
        }

        fwrite($pipes[0], trim($text));
        fclose($pipes[0]);
        $audio = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);

        if ($status !== 0 || $audio === '') {
            throw new \RuntimeException('eSpeak synthesis failed: ' . trim($error));
        }

        return $audio;
    }
}
