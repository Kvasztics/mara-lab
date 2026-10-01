<?php

declare(strict_types=1);

namespace mara\core\stt;

use mara\core\App;

final class SttManager
{
    /**
     * @param array<string, mixed> $upload
     * @return array{success: bool, text?: string, error?: string, started?: bool}
     */
    public function transcribe(array $upload): array
    {
        if (
            strtolower((string)App::get('system.stt_provider', '')) !== 'whisper' ||
            !isset($upload['tmp_name'], $upload['error']) ||
            (int)$upload['error'] !== UPLOAD_ERR_OK ||
            !is_uploaded_file((string)$upload['tmp_name'])
        )
        {
            return [
                'success' => false,
                'error'   => 'STT_ERROR_UPLOAD'
            ];
        }

        $directory = is_dir('/dev/shm') ? '/dev/shm' : sys_get_temp_dir();
        $source = tempnam($directory, 'mara-stt-source-');
        $wave = tempnam($directory, 'mara-stt-wave-');

        if ($source === false || $wave === false)
        {
            return [
                'success' => false,
                'error'   => 'STT_ERROR_AUDIO'
            ];
        }

        try
        {
            if (!move_uploaded_file((string)$upload['tmp_name'], $source))
            {
                return [
                    'success' => false,
                    'error'   => 'STT_ERROR_UPLOAD'
                ];
            }

            $command = sprintf(
                'ffmpeg -y -i %s -ar 16000 -ac 1 -c:a pcm_s16le -f wav %s 2>/dev/null',
                escapeshellarg($source),
                escapeshellarg($wave)
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0 || !is_file($wave) || filesize($wave) === 0)
            {
                return [
                    'success' => false,
                    'error'   => 'STT_ERROR_AUDIO'
                ];
            }

            return (new WhisperProvider())->transcribe($wave);
        }
        finally
        {
            @unlink($source);
            @unlink($wave);
        }
    }
}
