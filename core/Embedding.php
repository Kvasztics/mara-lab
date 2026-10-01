<?php
namespace mara\core;

use mara\core\provider\embedding\EmbeddingProvider;
use mara\core\provider\embedding\EmbeddingFactory;

/**
 * Embedding service
 */
final class Embedding
{
    private EmbeddingProvider $provider;

    public function __construct()
      {
        $this->provider = EmbeddingFactory::create();
      }

    /**
     * Generate embedding vector
     *
     * @param string $text
     * @return array
     */
    public function embed(string $text): array
      {
        return $this->provider->embed($text);
      }

    /**
     * Calculate cosine similarity
     *
     * @param array $a
     * @param array $b
     * @return float
     */
    public function similarity(
        array $a,
        array $b
    ): float
      {
        if (empty($a) || empty($b))
          {
            return 0.0;
          }

        if (count($a) !== count($b))
          {
            return 0.0;
          }

        $dot   = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value)
          {
            $valueB = $b[$i];

            $dot   += $value * $valueB;
            $normA += $value * $value;
            $normB += $valueB * $valueB;
          }

        if ($normA == 0.0 || $normB == 0.0)
          {
            return 0.0;
          }

        return $dot / (
            sqrt($normA) * sqrt($normB)
        );
      }
}