<?php
declare(strict_types=1);

namespace mara\core;

final class ServiceManager
{
    private const HELPER = '/usr/local/bin/mara-service';

    public function run(
        string $action,
        string $service,
        int $timeout = 35
    ): array {
        if (!in_array($action, ['start', 'stop', 'status'], true)) {
            throw new \InvalidArgumentException('Unsupported service action.');
        }

        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,63}$/D', $service)) {
            throw new \InvalidArgumentException('Invalid service identifier.');
        }

        if (!is_executable(self::HELPER)) {
            throw new \RuntimeException('Service helper is unavailable.');
        }

        $timeout = max(1, min(120, $timeout));
        $output = tmpfile();

        if ($output === false) {
            throw new \RuntimeException('Cannot create service output buffer.');
        }

        try {
            $process = proc_open(
                ['/usr/bin/sudo', '-n', self::HELPER, $action, $service],
                [
                    0 => ['file', '/dev/null', 'r'],
                    1 => $output,
                    2 => $output,
                ],
                $pipes
            );

            if (!is_resource($process)) {
                throw new \RuntimeException('Cannot execute service helper.');
            }

            $deadline = microtime(true) + $timeout;
            $timedOut = false;
            $exitCode = -1;

            try {
                while (true) {
                    $state = proc_get_status($process);

                    if (!$state['running']) {
                        $exitCode = (int)$state['exitcode'];
                        break;
                    }

                    if (microtime(true) >= $deadline) {
                        $timedOut = true;
                        proc_terminate($process, 9);
                        break;
                    }

                    usleep(100000);
                }
            } finally {
                $closedCode = proc_close($process);
                if ($exitCode < 0 && !$timedOut) {
                    $exitCode = $closedCode;
                }
            }

            rewind($output);
            $details = (string)stream_get_contents($output, 4096);

            if ($timedOut) {
                throw new \RuntimeException('Service helper timed out.');
            }

            return [
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'output' => trim($details),
            ];
        } finally {
            fclose($output);
        }
    }
}
