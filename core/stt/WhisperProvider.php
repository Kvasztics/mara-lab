<?php

declare(strict_types=1);

namespace mara\core\stt;

use mara\core\App;

final class WhisperProvider implements SttProviderInterface
{
    private array $config;

    public function __construct()
    {
        $this->config = App::get('stt.whisper', []);
    }

    public function transcribe(string $filePath): array
    {
        $started = false;

        if (!$this->isRunning())
        {
            $started = true;

            if (!$this->start())
            {
                return [
                    'success' => false,
                    'error'   => $this->startError(),
                    'started' => true
                ];
            }
        }

        $file = curl_file_create($filePath, 'audio/wav', 'audio.wav');

        $payload = [
            'file'            => $file,
            'language'        => $this->value('language', 'hu'),
            'temperature'     => $this->value('temperature', '0'),
            'temperature_inc' => $this->value('temperature_inc', '0.2'),
            'best_of'         => $this->value('best_of', '2'),
            'beam_size'       => $this->value('beam_size', '-1'),
            'no_speech_thold' => $this->value('no_speech_thold', '0.6'),
            'no_context'      => $this->enabled('use_context') ? 'false' : 'true',
            'response_format' => 'json'
        ];

        $curl = curl_init($this->url('/inference'));

        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 120,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);

        curl_close($curl);

        if ($response === false || $httpCode !== 200)
        {
            error_log('Whisper inference failed: '.$curlError);

            return [
                'success' => false,
                'error'   => 'STT_ERROR_SERVER',
                'started' => $started
            ];
        }

        $data = json_decode(trim((string)$response), true);
        $text = is_array($data) ? trim((string)($data['text'] ?? '')) : '';

        if (!is_array($data))
        {
            return [
                'success' => false,
                'error'   => 'STT_ERROR_RESPONSE',
                'started' => $started
            ];
        }

        if ($text === '')
        {
            return [
                'success' => false,
                'error'   => 'STT_ERROR_EMPTY',
                'started' => $started
            ];
        }

        return [
            'success' => true,
            'text'    => $text,
            'started' => $started
        ];
    }

    private function isRunning(): bool
    {
        $curl = curl_init($this->url('/'));

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT        => 2,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        return $response !== false &&
            $httpCode === 200 &&
            str_contains((string)$response, 'Whisper.cpp Server');
    }

    private function start(): bool
    {
        $directory = rtrim($this->value('dir'), '/');
        $binary = $directory.'/build/bin/whisper-server';
        $model = $this->modelPath($directory);

        if (!is_file($binary) || !is_executable($binary) || !is_file($model))
        {
            return false;
        }

        $command = [
            $binary,
            '-m', $model,
            '--host', $this->value('host', '127.0.0.1'),
            '--port', $this->value('port', '8000'),
            '-l', $this->value('language', 'hu')
        ];

        $escapedCommand = implode(
            ' ',
            array_map('escapeshellarg', $command)
        );

        /*
         * The server must outlive this HTTP request.  nohup detaches it
         * without using a broad pkill-style process cleanup.
         */
        exec('nohup '.$escapedCommand.' >/dev/null 2>&1 &');

        for ($attempt = 0; $attempt < 20; $attempt++)
        {
            usleep(250000);

            if ($this->isRunning())
            {
                return true;
            }
        }

        return false;
    }

    private function startError(): string
    {
        $directory = rtrim($this->value('dir'), '/');
        $binary = $directory.'/build/bin/whisper-server';

        if (!is_file($binary) || !is_executable($binary))
        {
            return 'STT_ERROR_BINARY';
        }

        if (!is_file($this->modelPath($directory)))
        {
            return 'STT_ERROR_MODEL';
        }

        return 'STT_ERROR_START';
    }

    private function modelPath(string $directory): string
    {
        $model = $this->value('model');

        return str_starts_with($model, '/')
            ? $model
            : $directory.'/'.$model;
    }

    private function url(string $path): string
    {
        return 'http://'.
            $this->value('host', '127.0.0.1').
            ':'.
            $this->value('port', '8000').
            $path;
    }

    private function value(string $key, string $default = ''): string
    {
        return trim((string)($this->config[$key] ?? $default));
    }

    private function enabled(string $key): bool
    {
        return $this->value($key) === '1';
    }
}
