<?php

declare(strict_types=1);

namespace mara\core;

use mara\database\Database;

final class SpladeMemory
{
    public static function encode(string $text): array
    {
        $dir = '/mnt/AI-Storage/models/crispembed-linux-x86_64';
        $model = dirname($dir) . '/Splade_v3/splade-v3-q8_0.gguf';
        $process = proc_open(
            ['/usr/bin/timeout', '--kill-after=1s', '3s', '/usr/bin/env',
             'LD_LIBRARY_PATH=' . $dir, $dir . '/crispembed', '-m', $model,
             '--sparse', '--', mb_substr(trim($text), 0, 4000)],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $dir
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start CrispEmbed.');
        }
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($process);
        if ($status !== 0 || $output === false) {
            throw new \RuntimeException('CrispEmbed failed (exit ' . $status . ').');
        }

        preg_match_all('/^[ \t]*(\d+)[ \t]+([0-9.eE+-]+)[ \t]*$/m', $output, $matches, PREG_SET_ORDER);
        $vector = [];
        foreach ($matches as [, $id, $weight]) {
            $value = (float)$weight;
            if (is_finite($value) && $value > 0) {
                $vector[(int)$id] = $value;
            }
        }
        return $vector;
    }

    public static function index(int $limit): int
    {
        $db = Database::getInstance()->getConnection();
        $rows = $db->execute_query(
            "SELECT id, content FROM chat_memory
             WHERE superseded_at IS NULL AND kind IN ('project', 'context') AND splade_vector IS NULL
             ORDER BY id LIMIT ?",
            [$limit]
        );
        $count = 0;
        while ($row = $rows->fetch_assoc()) {
            $vector = json_encode(self::encode($row['content']), JSON_THROW_ON_ERROR);
            $db->execute_query(
                'UPDATE chat_memory SET splade_vector = ? WHERE id = ? AND splade_vector IS NULL',
                [$vector, $row['id']]
            );
            $count += $db->affected_rows;
        }
        return $count;
    }
}
