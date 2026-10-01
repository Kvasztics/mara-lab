<?php
namespace mara\core\provider\embedding;

use mara\core\App;

/**
 * Embedding provider factory
 */
final class EmbeddingFactory
{
    /**
     * Create configured embedding provider
     *
     * @return EmbeddingProvider
     */
    public static function create(): EmbeddingProvider
      {
        $provider = strtolower(
            (string)(
                App::get('system')['embedding_provider']
                ?? ''
            )
        );

        return match ($provider)
          {
            'ollama' => new OllamaEmbedding(),
            default  => throw new \RuntimeException(
                'Unknown embedding provider: '.$provider
            )
          };
      }
}
?>