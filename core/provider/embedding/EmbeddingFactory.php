<?php
declare(strict_types=1);

namespace mara\core\provider\embedding;

use mara\core\App;
use mara\core\EmbeddingException;

final class EmbeddingFactory
{
    public static function create(array $config): EmbeddingProvider
    {
        if ($config['model'] === '') {
            throw new EmbeddingException(LANG['RAG_EMBEDDING_CONFIG_ERROR']);
        }

        return match ($config['provider']) {
            'ollama' => new HttpEmbedding(
                rtrim((string)App::get('provider.ollama.url', 'http://127.0.0.1:11434'), '/') . '/api/embed',
                $config['model'],
                'ollama'
            ),
            'llamacpp' => new HttpEmbedding(
                'http://127.0.0.1:' . $config['port'] . '/v1/embeddings',
                $config['model'],
                'llamacpp'
            ),
            default => throw new EmbeddingException(LANG['RAG_EMBEDDING_CONFIG_ERROR']),
        };
    }
}
