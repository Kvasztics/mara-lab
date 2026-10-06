<?php
declare(strict_types=1);

namespace mara\core\integration;

final class ImageJob
{
    private $lock = null;
    private $gate = null;

    private static function path(string $url): string
    {
        return sys_get_temp_dir() . '/mara-image-'
            . hash('sha256', rtrim($url, '/'));
    }

    public function __construct(
        string $url,
        int $userId,
        string $jobId
    ) {
        $path = self::path($url);
        $this->gate = fopen($path . '.gate', 'c+');
        $this->lock = fopen($path . '.lock', 'c+');

        if ($this->gate === false || $this->lock === false) {
            $this->close();
            throw new \RuntimeException('Cannot open image job lock.');
        }

        if (!flock($this->gate, LOCK_EX)) {
            $this->close();
            throw new \RuntimeException('Cannot lock image job gate.');
        }

        try {
            if (!flock($this->lock, LOCK_EX | LOCK_NB)) {
                throw new \RuntimeException('Image backend busy.', 409);
            }

            $data = json_encode([
                'user_id' => $userId,
                'job_id' => $jobId,
            ], JSON_THROW_ON_ERROR);

            if (
                !ftruncate($this->lock, 0) ||
                !rewind($this->lock) ||
                fwrite($this->lock, $data) !== strlen($data) ||
                !fflush($this->lock)
            ) {
                throw new \RuntimeException('Cannot register image job.');
            }
        } catch (\Throwable $error) {
            flock($this->gate, LOCK_UN);
            $this->close();
            throw $error;
        }

        flock($this->gate, LOCK_UN);
    }

    public static function control(
        string $url,
        int $userId,
        string $jobId,
        callable $callback
    ): array {
        $path = self::path($url);
        $gate = fopen($path . '.gate', 'c+');
        $lock = fopen($path . '.lock', 'c+');

        try {
            if ($gate === false || $lock === false || !flock($gate, LOCK_EX)) {
                throw new \RuntimeException('Cannot inspect image job.');
            }

            if (flock($lock, LOCK_EX | LOCK_NB)) {
                flock($lock, LOCK_UN);
                return ['active' => false];
            }

            rewind($lock);
            $owner = json_decode(stream_get_contents($lock), true);
            if (
                !is_array($owner) ||
                ($owner['user_id'] ?? null) !== $userId ||
                !is_string($owner['job_id'] ?? null) ||
                !hash_equals($owner['job_id'], $jobId)
            ) {
                throw new \InvalidArgumentException('Image job ownership mismatch.');
            }

            $result = $callback();
            if (($result['cancel_requested'] ?? false) === true) {
                $owner['cancel_requested'] = true;
                $encoded = json_encode($owner, JSON_THROW_ON_ERROR);
                if (
                    !ftruncate($lock, 0) ||
                    !rewind($lock) ||
                    fwrite($lock, $encoded) !== strlen($encoded) ||
                    !fflush($lock)
                ) {
                    throw new \RuntimeException('Cannot record cancellation.');
                }
            }

            return ['active' => true] + $result;
        } finally {
            if (is_resource($lock)) {
                fclose($lock);
            }
            if (is_resource($gate)) {
                flock($gate, LOCK_UN);
                fclose($gate);
            }
        }
    }

    public function wasCancelled(): bool
    {
        if (!is_resource($this->lock)) {
            return false;
        }
        if (!flock($this->gate, LOCK_EX)) {
            throw new \RuntimeException('Cannot inspect cancellation.');
        }
        try {
            rewind($this->lock);
            $data = json_decode(stream_get_contents($this->lock), true);
            return is_array($data) && ($data['cancel_requested'] ?? false) === true;
        } finally {
            flock($this->gate, LOCK_UN);
        }
    }

    public function close(): void
    {
        if (is_resource($this->gate)) {
            flock($this->gate, LOCK_EX);
        }
        if (is_resource($this->lock)) {
            fclose($this->lock);
            $this->lock = null;
        }
        if (is_resource($this->gate)) {
            flock($this->gate, LOCK_UN);
            fclose($this->gate);
            $this->gate = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
