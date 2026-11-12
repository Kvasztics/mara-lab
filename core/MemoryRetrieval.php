<?php

declare(strict_types=1);

namespace mara\core;

use mara\database\ChatMemory;

final class MemoryRetrieval
{
    private array $config;
    private string $key;

    public function __construct(private ChatMemory $db, Rag $rag)
    {
        $this->config = $rag->embeddingConfig();
        $this->key = hash('sha256', json_encode($this->config, JSON_THROW_ON_ERROR));
    }

    public function vector(string $content): array
    {
        $embedding = new Embedding($this->config);
        return [$this->key, json_encode($embedding->embed($content), JSON_THROW_ON_ERROR)];
    }

    public function search(int $userId, int $modelId, string $query): array
    {
        $profiles = $this->db->profiles($userId, $modelId);
        $candidates = $this->db->indexed($userId, $modelId, $this->key);
        if (trim($query) === '' || $candidates === []) {
            return $profiles;
        }

        try {
            $embedding = new Embedding($this->config + ['timeout' => 3]);
            $queryVector = $embedding->embed($query);
        } catch (\Throwable $error) {
            error_log('Mara memory embedding: ' . $error->getMessage());
            return $profiles;
        }

        $matches = [];
        foreach ($candidates as $row) {
            $vector = json_decode($row['embedding'], true);
            if (!is_array($vector)) {
                continue;
            }
            $score = $embedding->similarity($queryVector, $vector);
            if ($score >= 0.5) {
                $matches[] = ['content' => $row['content'], 'score' => $score, 'id' => $row['id']];
            }
        }
        usort($matches, static fn(array $a, array $b): int =>
            ($b['score'] <=> $a['score']) ?: ($b['id'] <=> $a['id'])
        );

        return array_merge($profiles, array_slice($matches, 0, 3));
    }

    public function reindex(): int
    {
        $count = 0;
        foreach ($this->db->pendingIndex($this->key) as $row) {
            [$key, $vector] = $this->vector($row['content']);
            $this->db->storeVector((int) $row['id'], $key, $vector);
            ++$count;
        }
        return $count;
    }
}
