<?php

declare(strict_types=1);

namespace mara\core\provider;

final class OllamaProvider implements ProviderInterface
{
    private string $baseUrl;
    private string $service;
    private int $startupTimeout;
    private int $startupPollMs;
    private ?string $modelId = null;
    private ?array $model = null;

    public function __construct(array $config)
    {
        $this->baseUrl        = rtrim((string)($config['url'] ?? ''), '/');
        $this->service        = (string)($config['service'] ?? 'ollama.service');
        $this->startupTimeout = (int)($config['startup_timeout'] ?? 60);
        $this->startupPollMs  = (int)($config['startup_poll_ms'] ?? 250);
    }

    public function isAvailable(): bool
    {
        try {
            $this->request('GET', '/api/tags');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Start the Ollama service without selecting a model.
     */
    public function start(): bool
    {
        return $this->startService();
    }

public function capabilities(?string $modelId = null): array
  {
    if ($modelId === null)
      {
        if ($this->modelId === null)
          {
            return [];
          } else
          {
            $modelId = $this->modelId;
          }
      }

    try
      {
        $response = $this->request(
          'POST',
          '/api/show',
          [
            'model' => $modelId,
          ]
        );
      }
    catch (\Throwable)
      {
        return [];
      }

    $caps    = $response['capabilities'] ?? [];
    $details = $response['details'] ?? [];
    $family  = $details['family'] ?? '';
    $context = 0;
    if ($family !== '')
      {
        $contextKey = $family.'.context_length';
        $context = (int)($response['model_info'][$contextKey] ?? 0);
      }
    return [
      'size'         => (string)($details['parameter_size'] ?? ''),
      'quantization' => (string)($details['quantization_level'] ?? ''),
      'context'      => $context,

      'vision'   => in_array('vision', $caps, true),
      'video'    => in_array('video', $caps, true),
      'audio'    => in_array('audio', $caps, true),
      'tools'    => in_array('tools', $caps, true),
      'thinking' => in_array('thinking', $caps, true),
    ];
  }

    public function prepare(array $model): bool
    {
        $this->model = null;

        $baseModel = (string)($model['basemodel'] ?? '');

        if ($baseModel === '') {
            $this->modelId = null;
            return false;
        }

        if (!$this->startService()) {
            $this->modelId = null;
            return false;
        }

        try {
            $models = $this->models();

            foreach ($models as $availableModel) {
                $name = (string)($availableModel['name'] ?? '');

                if ($name === $baseModel) {
                    $this->modelId = $baseModel;
                    $this->model   = $model;

                    return true;
                }
            }
        } catch (\Throwable) {
            $this->modelId = null;
            return false;
        }

        $this->modelId = null;

        return false;
    }

    public function stop(): bool
    {
        if (!$this->isAvailable()) {
            $this->modelId = null;
            $this->model   = null;

            return true;
        }

        try {
            $service = preg_replace('/\.service$/', '', $this->service);
            $result = (new \mara\core\ServiceManager())->run(
                'stop',
                $service
            );

            if (!$result['success']) {
                error_log('Ollama service stop: ' . $result['output']);
                return false;
            }
        } catch (\Throwable $error) {
            error_log('Ollama service stop: ' . $error->getMessage());
            return false;
        }

        if ($this->isAvailable()) {
            return false;
        }

        $this->modelId = null;
        $this->model   = null;

        return true;
    }

public function chat(
    array $messages,
    array $options = [],
    array $tools = []
): ProviderResponse
  {
    if ($this->modelId === null)
      {
        throw new \RuntimeException(
            'A provider nincs előkészítve modell használatára.'
        );
      }

    foreach ($messages as &$message) {
        if (!empty($message['images'])) {
            $message['images'] = array_map(static function ($image) {
                return preg_replace('~^data:image/[^;]+;base64,~', '', $image);
            }, $message['images']);
        }
    }
    unset($message);
    $payload = [
        'model'     => $this->modelId,
        'messages' => $messages,
        'stream'   => false,
    ];

    if (!empty($tools))
      {
        $payload['tools'] = $tools;
      }

    // DRY is a llama.cpp sampling feature; do not send it to Ollama.
    foreach (['dry_multiplier', 'dry_base', 'dry_allowed_length',
              'dry_penalty_last_n', 'dry_sequence_breakers'] as $key) {
        unset($options[$key]);
    }

    // Translate Mara's output-token limit to Ollama's native option.
    if (array_key_exists('max_tokens', $options)) {
        $options['num_predict'] = (int)$options['max_tokens'];
    }

    // These settings are handled by Mara or at the payload's top level.
    foreach ([
        'max_tokens', 'think', 'context_limit',
        'capabilities', 'builtin_tools'
    ] as $key) {
        unset($options[$key]);
    }

    if (!empty($options))
      {
        $payload['options'] = $options;
      }

    /*
     * Thinking kezelése.
     *
     * Csak thinking-képes modellnél küldjük.
     * Az érték a Mara modell konfigurációjából érkezik.
     */
    $capabilities = $this->capabilities();

    if (
        ($capabilities['thinking'] ?? false) &&
        $this->model !== null
    )
      {
        $payload['think'] = (bool)(
            $this->model['thinking'] ?? false
        );
      }





    $response = $this->request(
        'POST',
        '/api/chat',
        $payload
    );





    $content = $response['message']['content'] ?? null;

    if (!is_string($content))
      {
        throw new \RuntimeException(
            'Az Ollama válasza nem tartalmaz üzenetet.'
        );
      }

    $reasoning = $response['message']['thinking'] ?? null;

    if (!is_string($reasoning))
      {
        $reasoning = null;
      }

    return new ProviderResponse(
        content: $content,
        model: $this->modelId,
        finishReason: $response['done_reason'] ?? null,
        usage: [
            'prompt_tokens'     => $response['prompt_eval_count'] ?? 0,
            'completion_tokens' => $response['eval_count'] ?? 0,
        ],
        meta: $response,
        reasoning: $reasoning,
        toolCalls: $response['message']['tool_calls'] ?? [],
    );
  }

    public function models(): array
    {
        $response = $this->request('GET', '/api/tags');

        return $response['models'] ?? [];
    }

    private function request(
        string $method,
        string $endpoint,
        ?array $payload = null
    ): array {
        $tracked = $method === 'POST' && $endpoint === '/api/chat';
        $requestId = uniqid('ollama-', true);
        $started = microtime(true);
        $curl = null;
        $raw = null;
        $status = 0;
        $curlCode = 0;
        $failure = null;

        if ($tracked) {
            $settings = $payload ?? [];
            unset($settings['messages']);
            \mara\core\ModelCommunicationLog::write([
                'event' => 'REQUEST',
                'request_id' => $requestId,
                'provider' => 'ollama',
                'endpoint' => $endpoint,
                'model' => $payload['model'] ?? null,
                'settings' => $settings,
                'message_count' => count($payload['messages'] ?? []),
            ]);
        }

        try {
            $curl = curl_init($this->baseUrl . $endpoint);
            if ($curl === false) {
                throw new \RuntimeException('Nem sikerült inicializálni a cURL-t.');
            }

            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],

                CURLOPT_TIMEOUT => 120,
            ];

            if ($payload !== null) {
                $options[CURLOPT_POSTFIELDS] = json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                );
            }

            if (!curl_setopt_array($curl, $options)) {
                throw new \RuntimeException('A cURL beállítása sikertelen.');
            }

            $raw = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlCode = curl_errno($curl);

            if ($raw === false) {
                throw new \RuntimeException(
                    'Ollama kapcsolat hiba: ' . curl_error($curl)
                );
            }
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException("Ollama HTTP hiba: {$status}");
            }

            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Ollama: nem megfelelő JSON-válasz.');
            }
            return $decoded;
        } catch (\Throwable $error) {
            $failure = $error->getMessage();
            throw $error;
        } finally {
            if ($curl !== null && $curl !== false) {
                curl_close($curl);
            }
            if ($tracked) {
                \mara\core\ModelCommunicationLog::write([
                    'event' => $failure === null ? 'RESPONSE' : 'ERROR',
                    'request_id' => $requestId,
                    'provider' => 'ollama',
                    'model' => $payload['model'] ?? null,
                    'http_status' => $status,
                    'duration_seconds' => round(microtime(true) - $started, 3),
                    'curl_errno' => $curlCode,
                    'error' => $failure,
                    'raw_response' => is_string($raw) ? $raw : null,
                ]);
            }
        }
    }
    private function startService(): bool
    {
        if ($this->isAvailable()) {
            return true;
        }

        try {
            $service = preg_replace('/\.service$/', '', $this->service);
            $result = (new \mara\core\ServiceManager())->run(
                'start',
                $service
            );

            if (!$result['success']) {
                error_log('Ollama service start: ' . $result['output']);
                return false;
            }
        } catch (\Throwable $error) {
            error_log('Ollama service start: ' . $error->getMessage());
            return false;
        }

        $maxAttempts = (int) ceil(
            ($this->startupTimeout * 1000)
            / $this->startupPollMs
        );

        for ($i = 0; $i < $maxAttempts; $i++) {
            if ($this->isAvailable()) {
                return true;
            }

            usleep($this->startupPollMs * 1000);
        }

        return false;
    }


}
?>
