<?php
declare(strict_types=1);
namespace mara\core;

final class GpuMonitor
{
    private static function command(array $arguments): ?string
    {
        if (!function_exists('proc_open')) return null;
        $binary = '/usr/bin/nvidia-smi';
        if (!is_executable($binary)) return null;
        $process = @proc_open(array_merge([$binary], $arguments),
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($process)) return null;
        stream_set_blocking($pipes[1], false);
        $output = '';
        $deadline = microtime(true) + 3;
        $exit = -1;
        do {
            $output .= (string)stream_get_contents($pipes[1], 65536 - strlen($output));
            $status = proc_get_status($process);
            if (!$status['running']) { $exit = (int)$status['exitcode']; break; }
            if (microtime(true) >= $deadline || strlen($output) >= 65536) {
                proc_terminate($process, 9);
                break;
            }
            usleep(20000);
        } while (true);
        fclose($pipes[1]);
        proc_close($process);
        return $exit === 0 ? $output : null;
    }

    public static function read(bool $details = false): array
    {
        $text = self::command(['--query-gpu=index,uuid,name,memory.total,memory.used,memory.free,utilization.gpu,temperature.gpu', '--format=csv,noheader,nounits']);
        if ($text === null || trim($text) === '') {
            return ['available' => false, 'message' => 'NVIDIA GPU-adatok nem elérhetők ezen a szerveren.'];
        }
        $gpus = [];
        foreach (explode("\n", trim($text)) as $line) {
            $row = array_map('trim', str_getcsv($line));
            if (count($row) !== 8 || !is_numeric($row[3]) || !is_numeric($row[4]) || (float)$row[3] <= 0) continue;
            $gpu = ['index' => (int)$row[0], 'uuid' => $row[1], 'name' => $row[2],
                'total' => (float)$row[3], 'used' => (float)$row[4],
                'free' => is_numeric($row[5]) ? (float)$row[5] : null,
                'percent' => round(max(0, min(100, 100 * (float)$row[4] / (float)$row[3])), 1),
                'utilization' => is_numeric($row[6]) ? (float)$row[6] : null,
                'temperature' => is_numeric($row[7]) ? (float)$row[7] : null];
            $gpus[] = $gpu;
        }
        if (!$gpus) return ['available' => false, 'message' => 'A GPU-adatok nem értelmezhetők.'];
        $result = ['available' => true, 'gpus' => $gpus, 'updated' => gmdate('c')];
        if ($details) {
            $result['processes'] = [];
            $processText = self::command(['--query-compute-apps=gpu_uuid,pid,process_name,used_gpu_memory', '--format=csv,noheader,nounits']);
            $result['processes_available'] = $processText !== null;
            foreach (explode("\n", trim($processText ?? '')) as $line) {
                $row = array_map('trim', str_getcsv($line));
                if (count($row) !== 4 || !ctype_digit($row[1])) continue;
                $result['processes'][] = ['gpu_uuid' => $row[0], 'pid' => (int)$row[1],
                    'name' => $row[2], 'memory' => is_numeric($row[3]) ? (float)$row[3] : null];
            }
        }
        return $result;
    }
}
