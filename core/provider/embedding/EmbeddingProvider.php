<?php
namespace mara\core\provider\embedding;

/**
 * Embedding provider interface
 */
interface EmbeddingProvider
{
    /**
     * Generate embedding vector from text
     *
     * @param string $text
     * @return array
     */
    public function embed(string $text): array;
}