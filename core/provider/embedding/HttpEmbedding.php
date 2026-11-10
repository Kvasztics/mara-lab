<?php
declare(strict_types=1);

namespace mara\core\provider\embedding;

use mara\core\EmbeddingException;

final class HttpEmbedding implements EmbeddingProvider
{
    public function __construct(
        private string $endpoint,
        private string $model,
        private string $provider
    ) {
    }

    public function embed(string $text): array
    {
        $request = json_encode(['model' => $this->model, 'input' => $text], JSON_THROW_ON_ERROR);
        $curl = curl_init($this->endpoint);
        if ($curl === false) {
            throw new EmbeddingException(LANG['RAG_EMBEDDING_UNAVAILABLE']);
        }

        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_POSTFIELDS => $request,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 120,
            ]);
            $response = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($response === false || $status < 200 || $status >= 300) {
                error_log(sprintf('Mara RAG embedding (%s): HTTP %d, cURL %d',
                    $this->provider, $status, curl_errno($curl)));
                throw new EmbeddingException($response === false || $status >= 500
                    ? LANG['RAG_EMBEDDING_UNAVAILABLE']
                    : sprintf(LANG['RAG_EMBEDDING_HTTP_ERROR'], $status));
            }
        } finally {
            curl_close($curl);
        }

        $data = json_decode($response, true);
        if ($this->provider === 'llamacpp' && isset($data['model']) && $data['model'] !== $this->model) {
            throw new EmbeddingException(LANG['RAG_EMBEDDING_CONFIG_ERROR']);
        }
        $vector = $this->provider === 'ollama'
            ? ($data['embeddings'][0] ?? null)
            : ($data['data'][0]['embedding'] ?? null);
        if (!is_array($vector) || !array_is_list($vector) || $vector === []) {
            throw new EmbeddingException(LANG['RAG_EMBEDDING_INVALID']);
        }

        foreach ($vector as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value)) {
                throw new EmbeddingException(LANG['RAG_EMBEDDING_INVALID']);
            }
        }
        return $vector;
    }
}
