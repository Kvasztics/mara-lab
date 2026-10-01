<?php

declare(strict_types=1);

namespace mara\core\voice;

use mara\core\App;

final class VoiceManager
{
    public function speak(
        array $voice,
        string $text,
        bool $verifyAvailability = true
    ): void
    {
        $text = VoiceText::clean($text);

        if ($text === '') {
            throw new \RuntimeException('No speakable text.');
        }

        $provider = $this->provider((string)($voice['provider'] ?? ''));

        if ($verifyAvailability && !$provider->isAvailable()) {
            throw new \RuntimeException('Voice provider is not available.');
        }

        $audio = $provider->synthesize($voice, $text);

        /*
         * A binary response must begin with its own file signature.
         * Clear any whitespace buffered by the page/router before RIFF.
         */
        if (ob_get_level() > 0) {
            ob_clean();
        }

        header('Content-Type: ' . $this->audioContentType($audio));
        header('Content-Length: ' . strlen($audio));
        header('Cache-Control: no-store, max-age=0');
        header('Accept-Ranges: none');

        echo $audio;
    }

    private function provider(string $name): VoiceProviderInterface
    {
        return match (strtolower(trim($name))) {
            'xtts', 'f5tts' => new XTTSProvider(App::get('tts.xtts', [])),
            'piper' => new PiperProvider(App::get('tts.piper', [])),
            'espeak' => new ESpeakProvider(App::get('tts.espeak', [])),
            default => throw new \RuntimeException('Unknown voice provider.'),
        };
    }

    private function audioContentType(string $audio): string
    {
        if (
            strlen($audio) > 44
            && substr($audio, 0, 4) === 'RIFF'
            && substr($audio, 8, 4) === 'WAVE'
        ) {
            return 'audio/wav';
        }

        if (substr($audio, 0, 3) === 'ID3') {
            return 'audio/mpeg';
        }

        if (substr($audio, 0, 4) === 'OggS') {
            return 'audio/ogg';
        }

        $preview = trim(substr($audio, 0, 300));

        error_log('Invalid voice audio response: ' . $preview);

        throw new \RuntimeException(
            'Voice provider did not return supported audio.'
        );
    }
}
