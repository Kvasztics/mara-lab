<?php
namespace mara\core\provider\embedding;

use mara\core\App;

/**
 * Ollama embedding provider
 */
final class OllamaEmbedding implements EmbeddingProvider
{
    /**
     * Generate embedding vector from text
     *
     * @param string $text
     * @return array
     */
    public function embed(string $text): array
      {
        $ollama = App::get('provider')['ollama'];
        $system = App::get('system');

        $url = rtrim(
            (string)$ollama['url'],
            '/'
        ).'/api/embed';

        $model = (string)(
            $system['embedding_model'] ?? ''
        );

        if ($model === '')
          {
            throw new \RuntimeException(
                'Embedding model is not configured.'
            );
          }

        $payload = [
            'model' => $model,
            'input' => $text
        ];

        $curl = curl_init($url);

        if ($curl === false)
          {
            throw new \RuntimeException(
                'Embedding cURL initialization failed.'
            );
          }

        curl_setopt_array(
            $curl,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_POSTFIELDS     => json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE
                ),
                CURLOPT_TIMEOUT        => 120
            ]
        );

        $response = curl_exec($curl);

        if ($response === false)
          {
            $error = curl_error($curl);
            curl_close($curl);

            throw new \RuntimeException(
                'Ollama embedding error: '.$error
            );
          }

        $status = curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($curl);

        if ($status < 200 || $status >= 300)
          {
            throw new \RuntimeException(
                'Ollama embedding HTTP error: '.$status
            );
          }

        $data = json_decode(
            $response,
            true
        );

$data = json_decode(
    $response,
    true
);

return $data['embeddings'][0] ?? [];

        return $data['embeddings'][0] ?? [];
      }
}
?>