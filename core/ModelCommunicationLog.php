<?php
declare(strict_types=1);

namespace mara\core;

/** Diagnostic model responses; logging failures must not interrupt chat. */
final class ModelCommunicationLog
{
    public static function write(array $entry): void
    {
        $lock = null;
        $handle = null;

        try {
            $directory = dirname(__DIR__) . '/var/log';
            if (!is_dir($directory) &&
                !@mkdir($directory, 0750, true) &&
                !is_dir($directory)) {
                throw new \RuntimeException('Cannot create log directory.');
            }

            $path = $directory . '/model-communication.log';
            $lock = @fopen($path . '.lock', 'c');
            if ($lock === false || !flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock communication log.');
            }

            $entry = [
                'time' => date('c'),
                'user_id' => (int)($_SESSION['user']['id'] ?? 0),
                'chat_id' => (int)($_SESSION['chat_id'] ?? 0),
                'character_id' => (int)($_SESSION['model_id'] ?? 0),
            ] + $entry;

            $line = json_encode(
                $entry,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_INVALID_UTF8_SUBSTITUTE |
                JSON_THROW_ON_ERROR
            ) . "\n";

            clearstatcache(true, $path);
            $size = is_file($path) ? filesize($path) : 0;

            if ($size > 0 && $size + strlen($line) > 5 * 1024 * 1024) {
                if (is_file($path . '.3') && !@unlink($path . '.3')) {
                    throw new \RuntimeException('Cannot remove old log.');
                }
                for ($i = 2; $i >= 1; --$i) {
                    if (is_file($path . '.' . $i) &&
                        !@rename($path . '.' . $i, $path . '.' . ($i + 1))) {
                        throw new \RuntimeException('Cannot rotate old log.');
                    }
                }
                if (!@rename($path, $path . '.1')) {
                    throw new \RuntimeException('Cannot rotate current log.');
                }
            }

            $handle = @fopen($path, 'ab');
            if ($handle === false) {
                throw new \RuntimeException('Cannot open communication log.');
            }
            @chmod($path, 0640);

            $length = strlen($line);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($handle, substr($line, $offset));
                if ($written === false || $written === 0) {
                    throw new \RuntimeException('Cannot write communication log.');
                }
                $offset += $written;
            }
        } catch (\Throwable $error) {
            error_log('Mara communication log: ' . $error->getMessage());
        } finally {
            if (is_resource($handle)) fclose($handle);
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
