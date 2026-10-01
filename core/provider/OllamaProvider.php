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
    private string $serviceHelper = '/usr/local/bin/mara-service';
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

        $command = [
            'sudo',
            '-n',
            $this->serviceHelper,
            'stop',
            'ollama',
        ];

        $process = proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            return false;
        }

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
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

    $payload = [
        'model'    => $this->modelId,
        'messages' => $messages,
        'stream'   => false,
    ];

    if (!empty($tools))
      {
        $payload['tools'] = $tools;
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

    /*
     * Debug: raw Ollama request.
     */
    file_put_contents(
        DIR_ROOT.'/ollama_raw_debug.txt',
        "\n\n"
        . "============================================================\n"
        . "OLLAMA REQUEST: ".date('Y-m-d H:i:s')."\n"
        . "============================================================\n"
        . json_encode(
            $payload,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )
        . "\n",
        FILE_APPEND
    );

    error_log(
        'OLLAMA PAYLOAD TOOLS: '.json_encode(
            $payload['tools'] ?? null,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )
    );

    $response = $this->request(
        'POST',
        '/api/chat',
        $payload
    );

    /*
     * Debug: raw Ollama response.
     */
    file_put_contents(
        DIR_ROOT.'/ollama_raw_debug.txt',
        "\n"
        . "------------------------------------------------------------\n"
        . "OLLAMA RESPONSE: ".date('Y-m-d H:i:s')."\n"
        . "------------------------------------------------------------\n"
        . json_encode(
            $response,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )
        . "\n",
        FILE_APPEND
    );

    error_log(
        'OLLAMA TOOL CALLS: '.json_encode(
            $response['message']['tool_calls'] ?? null,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )
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
        $curl = curl_init($this->baseUrl . $endpoint);

        if ($curl === false) {
            throw new \RuntimeException(
                'Nem sikerült inicializálni a cURL-t.'
            );
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
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

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);

            throw new \RuntimeException(
                'Ollama kapcsolat hiba: ' . $error
            );
        }

        $status = curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($curl);

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                "Ollama HTTP hiba: {$status}"
            );
        }

        return json_decode(
            $response,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
    private function startService(): bool
    {
        if ($this->isAvailable()) {
            return true;
        }

        $command = [
            'sudo',
            '-n',
            $this->serviceHelper,
            'start',
            'ollama',
        ];

        $process = proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            return false;
        }

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
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
