<?php

declare(strict_types=1);

namespace mara\core\voice;

final class PiperProvider implements VoiceProviderInterface
{
    private string $binary;
    private string $modelDir;

    public function __construct(array $config)
    {
        $this->binary = trim((string)($config['binary'] ?? ''));
        $this->modelDir = rtrim((string)($config['model_dir'] ?? ''), '/');
    }

    public function isAvailable(): bool
    {
        return $this->binary !== ''
            && is_file($this->binary)
            && is_executable($this->binary)
            && $this->modelDir !== ''
            && is_dir($this->modelDir);
    }

    public function synthesize(array $voice, string $text): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Piper is not available.');
        }

        $model = $this->modelPath((string)($voice['sample'] ?? ''));

        if ($model === '' || !is_file($model) || trim($text) === '') {
            throw new \RuntimeException('Piper voice data is incomplete.');
        }

        $output = tempnam('/dev/shm', 'mara-piper-');

        if ($output === false) {
            throw new \RuntimeException('Cannot create Piper audio file.');
        }

        $command = escapeshellarg($this->binary)
            . ' --model ' . escapeshellarg($model)
            . ' --output_file ' . escapeshellarg($output);

        foreach ((array)($voice['parameters'] ?? []) as $key => $value) {
            if (in_array($key, ['length_scale', 'noise_w'], true)) {
                $command .= ' --' . $key . ' ' . escapeshellarg((string)(float)$value);
            }
        }

        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            @unlink($output);
            throw new \RuntimeException('Cannot start Piper.');
        }

        fwrite($pipes[0], trim($text) . "\n");
        fclose($pipes[0]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $audio = is_file($output) ? (string)file_get_contents($output) : '';
        @unlink($output);

        if ($status !== 0 || $audio === '') {
            throw new \RuntimeException('Piper synthesis failed: ' . trim($error));
        }

        return $audio;
    }

    private function modelPath(string $sample): string
    {
        $sample = trim($sample);

        if ($sample === '' || str_starts_with($sample, '/')) {
            return $sample;
        }

        return $this->modelDir . '/' . $sample;
    }
}
