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

    public function search(int $userId, int $modelId, string $query, int $chatId): array
    {
        $profiles = $this->db->profiles($userId, $modelId);
        if (trim($query) === '') {
            return $profiles;
        }
        if (App::get('system.memory_search', 'dense') === 'splade') {
            try {
                $rows = $this->db->indexed($userId, $modelId, $this->key, $chatId, true);
                if ($rows !== []) {
                    $queryVector = SpladeMemory::encode($query);
                    $matches = [];
                    foreach ($rows as $row) {
                        $vector = json_decode($row['embedding'], true, 512, JSON_THROW_ON_ERROR);
                        $score = 0.0;
                        foreach ($queryVector as $id => $weight) {
                            $score += $weight * ($vector[$id] ?? 0.0);
                        }
                        if ($score > 0) {
                            $row['score'] = $score;
                            unset($row['embedding']);
                            $matches[] = $row;
                        }
                    }
                    return array_merge($profiles, $this->bestMatches($matches));
                }
            } catch (\Throwable $error) {
                error_log('Mara SPLADE: ' . $error->getMessage());
            }
        }

        $candidates = $this->db->indexed($userId, $modelId, $this->key, $chatId);
        if ($candidates === []) {
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
        return array_merge($profiles, $this->bestMatches($matches));
    }

    private function bestMatches(array $matches): array
    {
        usort($matches, static fn(array $a, array $b): int =>
            ($b['score'] <=> $a['score']) ?: ($b['id'] <=> $a['id'])
        );

        return array_slice($matches, 0, 3);
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
