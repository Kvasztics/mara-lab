<?php

declare(strict_types=1);

namespace mara\core\voice;

final class XTTSProvider implements VoiceProviderInterface
{
    private string $url;
    private string $voiceDir;

    public function __construct(array $config)
    {
        $this->url = rtrim((string)($config['url'] ?? ''), '/');
        $this->voiceDir = rtrim((string)($config['voice_dir'] ?? ''), '/');
    }

    public function isAvailable(): bool
    {
        if ($this->url === '') {
            return false;
        }

        $ch = curl_init($this->url . '/openapi.json');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
        ]);

        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            return false;
        }

        $data = json_decode($response, true);

        return is_array($data)
            && isset($data['paths']['/synthesize']);
    }

    public function synthesize(array $voice, string $text): string
    {
        $sample = $this->samplePath((string)($voice['sample'] ?? ''));
        $refText = $this->prepareText((string)($voice['reftext'] ?? ''));
        $text = $this->prepareText($text);

        if ($sample === '' || !is_file($sample) || $refText === '' || $text === '') {
            throw new \RuntimeException('XTTS voice data is incomplete.');
        }

        $fields = [
            'ref_text' => $refText,
            'gen_text' => $text,
            'ref_audio' => new \CURLFile($sample, 'audio/wav', basename($sample)),
        ];

        foreach ((array)($voice['parameters'] ?? []) as $key => $value) {
            if (in_array($key, ['temperature', 'speed'], true)) {
                $fields[$key] = (string)$value;
            }
        }

        $ch = curl_init($this->url . '/synthesize');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 90,
        ]);

        $audio = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($audio === false || $status !== 200 || $audio === '') {
            throw new \RuntimeException('XTTS synthesis failed.');
        }

        return $audio;
    }

    private function samplePath(string $sample): string
    {
        $sample = trim($sample);

        if ($sample === '' || str_starts_with($sample, '/')) {
            return $sample;
        }

        return $this->voiceDir === '' ? $sample : $this->voiceDir . '/' . $sample;
    }

    private function prepareText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        return str_replace(['...', '-', 'w'], [', ', ', ', 'v'], $text);
    }
}
