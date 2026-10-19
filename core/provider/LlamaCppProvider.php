<?php

declare(strict_types=1);

namespace mara\core\provider;

use RuntimeException;

class LlamaCppProvider implements ProviderInterface
{
    private string $baseUrl;
  	private string $mode;
    private ?string $modelId = null;
    private string $binary;
    private string $modelDir;
    private string $pidFile;
    private string $logFile;   
    private int $startupTimeout;
    private int $startupPollMs;
    private int $gpuLayers;
    private int $parallel;     
    private ?array $model = null;
    private string $startupError = '';
    private int $launchedPid = 0;
    private int $startupLogOffset = 0;

    public function getStartupError(): string { return $this->startupError; }

    private function processAlive(int $pid): bool
    {
        if ($pid <= 0) return false;
        $stat = @file_get_contents('/proc/'.$pid.'/stat');
        if (is_string($stat)) {
            $end = strrpos($stat, ')');
            if ($end !== false && in_array(substr($stat, $end + 2, 1), ['Z', 'X'], true)) return false;
            return true;
        }
        return function_exists('posix_kill') && @posix_kill($pid, 0);
    }

    private function startupFailure(string $fallback): bool
    {
        $text = '';
        $handle = @fopen($this->logFile, 'rb');
        if ($handle !== false) {
            $size = (int)(fstat($handle)['size'] ?? 0);
            $offset = $size < $this->startupLogOffset ? 0 : $this->startupLogOffset;
            fseek($handle, max($offset, $size - 65536));
            $text = (string)stream_get_contents($handle, 65536);
            fclose($handle);
        }
        if (preg_match('/out of memory|unable to allocate CUDA|failed to allocate CUDA/i', $text)) {
            $fallback = 'A modell betöltése sikertelen: nincs elég szabad GPU-memória. Szabadíts fel VRAM-ot (például a képgenerátor leállításával), majd próbáld újra.';
        } elseif (preg_match('/address already in use|failed to bind|couldn.t bind HTTP server socket/i', $text)) {
            $fallback = 'A llama.cpp portját már egy másik folyamat használja.';
        } elseif (preg_match('/invalid gguf|invalid magic|unsupported.*architecture/i', $text)) {
            $fallback = 'A modellfájl hibás, vagy ezt a modellt a llama.cpp jelenlegi verziója nem támogatja.';
        }
        $this->startupError = $fallback;
        $this->modelId = null;
        if ($this->launchedPid > 0 && !$this->processAlive($this->launchedPid) &&
            (int)@file_get_contents($this->pidFile) === $this->launchedPid) @unlink($this->pidFile);
        return false;
    }

	public function __construct(array $config)
	{
		$this->baseUrl = rtrim(
			$config['url'] ?? 'http://127.0.0.1:8082',
			'/'
		);

		$this->mode = $config['mode'] ?? 'direct';
    $this->binary = $config['binary'] ?? '';
    $this->modelDir = rtrim($config['model_dir'] ?? '', '/');
    $this->pidFile = $config['pid_file'] ?? '/tmp/mara-llama.pid';
    $this->logFile = $config['log_file'] ?? '/tmp/mara-llama.log';
    $this->startupTimeout = max(1, min(120, (int)($config['startup_timeout'] ?? 60)));
    $this->startupPollMs  = max(50, min(1000, (int)($config['startup_poll_ms'] ?? 250)));
    $this->gpuLayers      = (int)($config['gpu_layers'] ?? 0);
    $this->parallel       = (int)($config['parallel'] ?? 1);        
	}

    public function stop(): bool
    {
        $stopped = match ($this->mode) {
            'direct', 'router' => $this->withDirectLock(fn(): bool => $this->stopDirectServer()),
            default  => false,
        };

        if ($stopped) {
            $this->model = null;
            $this->modelId = null;
        }

        return $stopped;
    }

    /**
     * A llama.cpp router által ismert modellek.
     */
    public function models(): array
    {
        return $this->request('GET', '/models');
    }

    /**
     * Mara basemodel név alapján megkeresi
     * a llama.cpp router modelljét.
     */
    public function resolveModel(string $baseModel): ?array
    {
        $modelId = pathinfo($baseModel, PATHINFO_FILENAME);

        $response = $this->models();

        foreach ($response['data'] ?? [] as $model) {
            if (($model['id'] ?? '') === $modelId) {
                return $model;
            }
        }

        return null;
    }

    /**
     * A provider elérhető-e.
     */
    public function isAvailable(): bool
    {
        try {
            $this->models();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

public function capabilities(?string $modelId = null): array
  {
    if ($this->modelId === null)
      {
        return [];
      }

    if ($this->model !== null && \mara\core\Models::hasCachedCapabilities($this->model)) {
        return $this->model['modelinfo'];
    }
    try
      {
        $props  = $this->request('GET', '/props');
        $models = $this->request('GET', '/v1/models');
      }
    catch (\Throwable)
      {
        return [];
      }

    // A failed/incomplete response must remain retryable, not become all false.
    if (!isset($props['modalities'], $props['chat_template_caps']) ||
        !is_array($props['modalities']) || !is_array($props['chat_template_caps'])) {
        return [];
    }
    $modalities = $props['modalities'] ?? [];
    $caps       = $props['chat_template_caps'] ?? [];
    $meta       = $models['data'][0]['meta'] ?? [];

    $nParams = (int)($meta['n_params'] ?? 0);

    return [
        'size'         => $nParams > 0
            ? round($nParams / 1000000000, 1) . 'B'
            : '',
        'quantization' => (string)($props['model_ftype'] ?? ''),
        'context'      => (int)($props['default_generation_settings']['n_ctx'] ?? 0),

        'vision' => (bool)($modalities['vision'] ?? false),
        'video'  => (bool)($modalities['video'] ?? false),
        'audio'  => (bool)($modalities['audio'] ?? false),

        'tools' => (bool)(
            ($caps['supports_tools'] ?? false) ||
            ($caps['supports_tool_calls'] ?? false)
        ),

        'thinking' => (bool)(
            ($caps['supports_thinking'] ?? false) ||
            ($caps['supports_preserve_reasoning'] ?? false) ||
            str_contains((string)($props['chat_template'] ?? ''), 'enable_thinking')
        ),
    ];
  }

/**
 * Read static GGUF metadata for a selectable model without starting llama-server.
 *
 * @param string $baseModel
 * @return array
 */
public function modelInfo(string $baseModel): array
  {
    $filename = basename($baseModel);

    if ($filename === '' || $this->modelDir === '')
      {
        throw new RuntimeException('GGUF model path is not available.');
      }

    $path = $this->modelDir.'/'.$filename;

    if (!is_file($path) || !is_readable($path))
      {
        throw new RuntimeException('GGUF model file is not readable.');
      }

    $handle = fopen($path, 'rb');

    if ($handle === false)
      {
        throw new RuntimeException('Unable to open GGUF model file.');
      }

    try
      {
        if (fread($handle, 4) !== 'GGUF')
          {
            throw new RuntimeException('Invalid GGUF model file.');
          }

        $version = $this->readGgufUInt32($handle);

        if ($version < 2 || $version > 3)
          {
            throw new RuntimeException('Unsupported GGUF version.');
          }

        $this->readGgufUInt64($handle); // tensor count
        $metadataCount = $this->readGgufUInt64($handle);
        $metadata = [];

        for ($index = 0; $index < $metadataCount; $index++)
          {
            $key  = $this->readGgufString($handle);
            $type = $this->readGgufUInt32($handle);

            $architecture = (string)(
                $metadata['general.architecture'] ?? ''
            );

            if (
                in_array($key, [
                'general.architecture',
                'general.size_label',
                'general.parameter_count',
                'general.file_type'
                ], true) ||
                ($architecture !== '' &&
                    $key === $architecture.'.context_length')
            )
              {
                $metadata[$key] = $this->readGgufValue($handle, $type);
              }
            else
              {
                $this->skipGgufValue($handle, $type);
              }
            if (
                $architecture !== '' &&
                array_key_exists($architecture.'.context_length', $metadata) &&
                (
                    !empty($metadata['general.size_label']) ||
                    !empty($metadata['general.parameter_count'])
                )
            )
              {
                break;
              }
          }
      }
    finally
      {
        fclose($handle);
      }

    $architecture = (string)($metadata['general.architecture'] ?? '');
    $size = trim((string)($metadata['general.size_label'] ?? ''));

    if ($size === '')
      {
        $parameterCount = (int)(
            $metadata['general.parameter_count'] ?? 0
        );

        if ($parameterCount > 0)
          {
            $size = round($parameterCount / 1000000000, 1).'B';
          }
      }

    $fileType = (int)($metadata['general.file_type'] ?? -1);

    return [
        'size' => $size,
        'quantization' => $this->ggufQuantization(
            $fileType,
            $filename
        ),
        'context' => (int)(
            $metadata[$architecture.'.context_length'] ?? 0
        ),
        'vision' => false,
        'video' => false,
        'audio' => false,
        'tools' => false,
        'thinking' => false,
        'capabilities_known' => false,
    ];
  }

private function readGgufUInt32($handle): int
  {
    $value = fread($handle, 4);

    if ($value === false || strlen($value) !== 4)
      {
        throw new RuntimeException('Unexpected end of GGUF metadata.');
      }

    return (int)unpack('V', $value)[1];
  }

private function readGgufUInt64($handle): int
  {
    $value = fread($handle, 8);

    if ($value === false || strlen($value) !== 8)
      {
        throw new RuntimeException('Unexpected end of GGUF metadata.');
      }

    return (int)unpack('P', $value)[1];
  }

private function readGgufString($handle): string
  {
    $length = $this->readGgufUInt64($handle);

    if ($length === 0)
      {
        return '';
      }

    $value = fread($handle, $length);

    if ($value === false || strlen($value) !== $length)
      {
        throw new RuntimeException('Unexpected end of GGUF string.');
      }

    return $value;
  }

private function readGgufValue($handle, int $type): mixed
  {
    return match ($type) {
        0 => ord(fread($handle, 1)),
        1 => unpack('c', fread($handle, 1))[1],
        2 => unpack('v', fread($handle, 2))[1],
        3 => unpack('s', fread($handle, 2))[1],
        4 => $this->readGgufUInt32($handle),
        5 => unpack('l', fread($handle, 4))[1],
        6 => unpack('g', fread($handle, 4))[1],
        7 => ord(fread($handle, 1)) !== 0,
        8 => $this->readGgufString($handle),
        10 => $this->readGgufUInt64($handle),
        11 => unpack('q', fread($handle, 8))[1],
        12 => unpack('e', fread($handle, 8))[1],
        default => throw new RuntimeException('Unsupported GGUF metadata type.')
    };
  }

private function skipGgufValue($handle, int $type): void
  {
    if ($type === 8)
      {
        $this->readGgufString($handle);
        return;
      }

    if ($type === 9)
      {
        $elementType = $this->readGgufUInt32($handle);
        $count = $this->readGgufUInt64($handle);

        if ($elementType === 8)
          {
            for ($index = 0; $index < $count; $index++)
              {
                $this->readGgufString($handle);
              }

            return;
          }

        $sizes = [
            0 => 1, 1 => 1, 2 => 2, 3 => 2, 4 => 4, 5 => 4,
            6 => 4, 7 => 1, 10 => 8, 11 => 8, 12 => 8
        ];

        if (!isset($sizes[$elementType]))
          {
            throw new RuntimeException('Unsupported GGUF metadata array.');
          }

        fseek($handle, $sizes[$elementType] * $count, SEEK_CUR);
        return;
      }

    $sizes = [
        0 => 1, 1 => 1, 2 => 2, 3 => 2, 4 => 4, 5 => 4,
        6 => 4, 7 => 1, 10 => 8, 11 => 8, 12 => 8
    ];

    if (!isset($sizes[$type]))
      {
        throw new RuntimeException('Unsupported GGUF metadata type.');
      }

    fseek($handle, $sizes[$type], SEEK_CUR);
  }

private function ggufQuantization(int $fileType, string $filename): string
  {
    $types = [
        0 => 'F32', 1 => 'F16', 2 => 'Q4_0', 3 => 'Q4_1',
        6 => 'Q5_0', 7 => 'Q5_1', 8 => 'Q8_0', 9 => 'Q8_1',
        10 => 'Q2_K', 11 => 'Q3_K_S', 12 => 'Q3_K_M',
        13 => 'Q3_K_L', 14 => 'Q4_K_S', 15 => 'Q4_K_M',
        16 => 'Q5_K_S', 17 => 'Q5_K_M', 18 => 'Q6_K',
        19 => 'Q8_K', 20 => 'IQ2_XXS', 21 => 'IQ2_XS',
        22 => 'IQ3_XXS', 23 => 'IQ1_S', 24 => 'IQ4_NL',
        25 => 'IQ3_S', 26 => 'IQ2_S', 27 => 'IQ4_XS',
        33 => 'IQ1_M', 34 => 'BF16'
    ];

    if (isset($types[$fileType]))
      {
        return $types[$fileType];
      }

    if (preg_match('/(?:IQ|Q)\d(?:_[A-Z0-9]+)+/i', $filename, $match))
      {
        return strtoupper($match[0]);
      }

    return '';
  }

    /**
     * Chat request.
     */
    public function chat(
        array $messages,
        array $options = [],
        array $tools = []
    ): ProviderResponse
    {
        if ($this->modelId === null) {
            throw new RuntimeException(
                'A provider nincs előkészítve modell használatára.'
            );
        }

        foreach ($messages as &$message) {
            if (empty($message['images'])) { continue; }
            $content = [['type' => 'text', 'text' => (string)$message['content']]];
            foreach ($message['images'] as $image) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $image]];
            }
            $message['content'] = $content;
            unset($message['images']);
        }
        unset($message);
        $payload = array_merge([
            'model'    => $this->modelId,
            'messages' => $messages,
        ], $options);

        if (!empty($tools))
          {
            $payload['tools'] = $tools;
          }

        // Read the saved parameter, preserving an explicit false request value.
        $thinking = filter_var(
            $options['think'] ?? $this->model['parameters']['think'] ?? $this->model['thinking'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        unset($payload['think']);
        $payload['chat_template_kwargs'] = array_merge(
            $payload['chat_template_kwargs'] ?? [],
            ['enable_thinking' => $thinking]
        );
        if (!$thinking) {
            $payload['reasoning_effort'] = 'none';
        }

        $response = $this->request(
            'POST',
            '/v1/chat/completions',
            $payload
        );

        $message = $response['choices'][0]['message'] ?? [];



        return new ProviderResponse(
            content: $message['content'] ?? '',
            model: $response['model'] ?? '',
            finishReason: $response['choices'][0]['finish_reason'] ?? null,
            usage: $response['usage'] ?? [],
            meta: [
                'id' => $response['id'] ?? null,
                'timings' => $response['timings'] ?? [],
                'eval_duration' => (int) round(
                    (float)($response['timings']['predicted_ms'] ?? 0) * 1000000
                ),
            ],           
            reasoning: $message['reasoning_content'] ?? null,
            toolCalls: $message['tool_calls'] ?? [],
        );
    }

    /**
     * HTTP request a llama.cpp API felé.
     */
    private function request(
        string $method,
        string $endpoint,
        ?array $payload = null
    ): array {
        $tracked = $method === 'POST' && $endpoint === '/v1/chat/completions';
        $requestId = uniqid('llamacpp-', true);
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
                'provider' => 'llamacpp',
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
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => $method === 'GET' && in_array($endpoint, ['/v1/models', '/health'], true) ? 2 : 600,
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
                    'Provider kapcsolat hiba: ' . curl_error($curl)
                );
            }
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException("Provider HTTP hiba: {$status}");
            }

            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Provider: nem megfelelő JSON-válasz.');
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
                    'provider' => 'llamacpp',
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
	
    public function prepare(array $model): bool
    {
        $this->model = null;
        $this->startupError = '';
        $this->launchedPid = 0;

        $baseModel = (string)($model['basemodel'] ?? '');
        $mmproj    = (string)($model['mmproj'] ?? '');
        $model['parameters']['think'] = filter_var(
            $model['parameters']['think'] ?? $model['thinking'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if ($baseModel === '') {
            $this->modelId = null;
            return false;
        }

        try {
        $prepared = match ($this->mode) {
            'direct' => $this->withDirectLock(fn(): bool => $this->prepareDirect($baseModel, $mmproj, $model['parameters'] ?? [])),
            'router' => $this->withDirectLock(fn(): bool => $this->prepareRouter((int)($model['id'] ?? 0))),
            default  => false,
        };
        } catch (RuntimeException $error) {
            $this->startupError = $error->getMessage();
            return false;
        }

        if (!$prepared) {
            return false;
        }

        $this->model = $model;
        if (!\mara\core\Models::hasCachedCapabilities($model)) {
            try {
                $capabilities = $this->capabilities();
                if (!empty($capabilities)) {
                    $info = (new \mara\core\Models())->rememberCapabilities($model, $capabilities);
                    if (!empty($info)) $this->model['modelinfo'] = $info;
                }
            } catch (\Throwable $error) {
                // Metadata collection must not prevent an otherwise working chat.
                error_log('llama capability cache: '.$error->getMessage());
            }
        }

        return true;
    }

	private function prepareRouter(int $maraModelId): bool
{
    $pid = (int)trim((string)@file_get_contents($this->pidFile));
    $runningMode = $this->managedProcessMode($pid);

    if ($runningMode === 'direct') {
        if (!$this->stopDirectServer()) {
            $this->modelId = null;
            return false;
        }
        $runningMode = null;
    }

    if ($runningMode !== 'router') {
        if (!$this->stopDirectServer()) {
            $this->modelId = null;
            return false;
        }

        if (!$this->startRouterServer()) {
            $this->modelId = null;
            return false;
        }
    }

    $routerModel = \mara\core\LlamaRouterPreset::modelName($maraModelId);

    if (!$this->loadModel($routerModel)) {
        $this->modelId = null;
        return false;
    }

    $model = $this->resolveModel($routerModel);

    if ($model === null) {
        $this->modelId = null;
        return false;
    }

    $this->modelId = $model['id'];
    return true;
}


    /** Serialize lifecycle operations across PHP workers. */
    private function withDirectLock(callable $operation): bool
    {
        $lock = @fopen($this->pidFile.'.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('A llama.cpp zárolási fájlja nem írható.');
        }
        try {
            $deadline = microtime(true) + $this->startupTimeout;
            while (!flock($lock, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Másik llama.cpp modellváltás van folyamatban. Próbáld újra.');
                }
                usleep($this->startupPollMs * 1000);
            }
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** A failed HTTP health check does not mean the listening socket is gone. */
    private function directPortOccupied(): bool
    {
        $host = (string)parse_url($this->baseUrl, PHP_URL_HOST);
        $port = (int)parse_url($this->baseUrl, PHP_URL_PORT);
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || $port <= 0) {
            throw new RuntimeException('Direct módban helyi llama.cpp URL és explicit port szükséges.');
        }
        $target = str_contains($host, ':') ? '['.$host.']' : $host;
        $socket = @stream_socket_client(
            'tcp://'.$target.':'.$port, $errno, $error, 0.3
        );
        if ($socket === false) return false;
        fclose($socket);
        return true;
    }

    /**
     * Identify a Mara-managed llama-server process.
     *
     * Returns 'direct', 'router', or null.
     */
    private function managedProcessMode(int $pid): ?string
    {
        if ($pid <= 0 || !$this->processAlive($pid)) {
            return null;
        }

        $storedPid = (int)trim(
            (string)@file_get_contents($this->pidFile)
        );

        if ($storedPid !== $pid) {
            return null;
        }

        $command = @file_get_contents('/proc/'.$pid.'/cmdline');

        if (!is_string($command) || $command === '') {
            return null;
        }

        $args = explode("\0", rtrim($command, "\0"));

        $actualBinary = realpath($args[0] ?? '');
        $expectedBinary = realpath($this->binary);

        if ($expectedBinary === false ||
            $actualBinary !== $expectedBinary) {
            return null;
        }

        if (!function_exists('posix_geteuid') ||
            @fileowner('/proc/'.$pid) !== posix_geteuid()) {
            return null;
        }

        $portIndex = array_search('--port', $args, true);

        if ($portIndex === false ||
            ($args[$portIndex + 1] ?? '') !==
                (string)parse_url($this->baseUrl, PHP_URL_PORT)) {
            return null;
        }

        $direct = in_array('--model', $args, true);
        $router = in_array('--models-dir', $args, true);

        if ($direct === $router) {
            return null;
        }

        return $direct ? 'direct' : 'router';
    }

    private function isManagedDirectProcess(int $pid): bool
    {
        return $this->managedProcessMode($pid) === 'direct';
    }

private function runtimeOptions(array $parameters): array
{
    $runtime = $parameters['runtime'] ?? [];

    if (!is_array($runtime)) {
        $runtime = [];
    }

    $integer = static function (
        string $key,
        int $default,
        int $min,
        int $max
    ) use ($runtime): int {
        $value = $runtime[$key] ?? $default;

        return max($min, min($max, (int)$value));
    };

    $choice = static function (
        string $key,
        string $default,
        array $allowed
    ) use ($runtime): string {
        $value = $runtime[$key] ?? $default;

        return is_string($value) && in_array($value, $allowed, true)
            ? $value
            : $default;
    };

    return [
        'gpu_layers'    => $integer('gpu_layers', $this->gpuLayers, 0, 999),
        'batch_size'    => $integer('batch_size', 128, 1, 8192),
        'ubatch_size'   => $integer('ubatch_size', 128, 1, 8192),
        'threads'       => $integer('threads', -1, -1, 256),
        'threads_batch' => $integer('threads_batch', -1, -1, 256),
        'flash_attn'    => $choice('flash_attn', 'auto', ['on', 'off', 'auto']),
        'cache_type_k'  => $choice('cache_type_k', 'f16', [
            'f32', 'f16', 'bf16', 'q8_0', 'q4_0', 'q4_1', 'q5_0', 'q5_1',
        ]),
        'cache_type_v'  => $choice('cache_type_v', 'f16', [
            'f32', 'f16', 'bf16', 'q8_0', 'q4_0', 'q4_1', 'q5_0', 'q5_1',
        ]),
        'cpu_moe'       => !empty($runtime['cpu_moe']),
        'n_cpu_moe'     => $integer('n_cpu_moe', 0, 0, 999),
        'n_cpu_ffn'     => $integer('n_cpu_ffn', 0, 0, 999),
    ];
}    

    private function directConfigurationMatches(
        string $baseModel,
        string $mmproj,
        array $parameters
    ): bool {
        $pid = (int)trim((string)@file_get_contents($this->pidFile));
        if (!$this->processAlive($pid) || !$this->isManagedDirectProcess($pid)) {
            return false;
        }
        $command = @file_get_contents('/proc/'.$pid.'/cmdline');
        if (!is_string($command) || $command === '') return false;
        $args = explode("\0", rtrim($command, "\0"));
        $value = static function (string $flag) use ($args): ?string {
            $index = array_search($flag, $args, true);
            return $index === false ? null : ($args[$index + 1] ?? null);
        };

        $expectedModel = realpath($this->modelDir.'/'.$baseModel);
        if ($expectedModel === false ||
            realpath($value('--model') ?? '') !== $expectedModel) {
            return false;
        }
        if ($mmproj === '') {
            if ($value('--mmproj') !== null) return false;
        } else {
            $expectedProjector = realpath($this->modelDir.'/'.$mmproj);
            if ($expectedProjector === false ||
                realpath($value('--mmproj') ?? '') !== $expectedProjector) {
                return false;
            }
        }

        $runtime = $this->runtimeOptions($parameters);

        

        // CPU-szálak: automatikus módban egyik kapcsoló sem szerepel.
        foreach ([
            '--threads' => $runtime['threads'],
            '--threads-batch' => $runtime['threads_batch'],
        ] as $flag => $expected) {
            if ($expected > 0) {
                if ($value($flag) !== (string)$expected) {
                    return false;
                }
            } elseif (in_array($flag, $args, true)) {
                return false;
            }
        }

        // A CPU-offload kapcsolókat is ellenőrizni kell.
        $hasFlag = static fn(string $flag): bool =>
            in_array($flag, $args, true);

        if ($hasFlag('--cpu-moe') !== $runtime['cpu_moe']) {
            return false;
        }

        $expectedMoeLayers = $runtime['cpu_moe']
            ? 0
            : $runtime['n_cpu_moe'];

        if ($expectedMoeLayers > 0) {
            if ($value('--n-cpu-moe') !== (string)$expectedMoeLayers) {
                return false;
            }
        } elseif ($hasFlag('--n-cpu-moe')) {
            return false;
        }

        if ($runtime['n_cpu_ffn'] > 0) {
            if ($value('--n-cpu-ffn') !== (string)$runtime['n_cpu_ffn']) {
                return false;
            }
        } elseif ($hasFlag('--n-cpu-ffn')) {
            return false;
        }

        $context = max(512, (int)($parameters['num_ctx'] ?? 4096))
            * max(1, $this->parallel);

        return
            $value('--ctx-size') === (string)$context &&
            $value('--parallel') === (string)$this->parallel &&
            $value('--n-gpu-layers') === (string)$runtime['gpu_layers'] &&
            $value('--batch-size') === (string)$runtime['batch_size'] &&
            $value('--flash-attn') === $runtime['flash_attn'] &&
            $value('--cache-type-k') === $runtime['cache_type_k'] &&
            $value('--cache-type-v') === $runtime['cache_type_v'] &&
            $value('--ubatch-size') === (string)min(
                $runtime['ubatch_size'],
                $runtime['batch_size']
            );
    }

private function prepareDirect(
    string $baseModel,
    string $mmproj = '',
    array $parameters = [],
    bool $forceRestart = false
): bool
{
      $modelId = pathinfo($baseModel, PATHINFO_FILENAME);

      // Már fut, és pontosan a kívánt modell van betöltve.
      $pid = (int)trim((string)@file_get_contents($this->pidFile));
      $runningMode = $this->managedProcessMode($pid);

      $runningModel = $runningMode === 'direct'
          ? $this->getDirectModel()
          : null;

      if (!$forceRestart &&
          $runningModel === $modelId &&
          $this->directConfigurationMatches($baseModel, $mmproj, $parameters)) {
          $this->modelId = $modelId;
          return true;
      }


      if ($runningModel !== null) {
          if (!$this->stopDirectServer()) {
              $this->modelId = null;
              return false;
          }
      }

      // Clear a still-live tracked process even if its HTTP API is unavailable.
      if (!$this->stopDirectServer()) {
          $this->modelId = null;
          return false;
      }

      // Nem fut llama-server: elindítjuk a kívánt modellel.
      if (!$this->startDirectServer($baseModel, $mmproj, $parameters)) {
          $this->modelId = null;
          return false;
      }

      $deadline = microtime(true) + $this->startupTimeout;
      while (microtime(true) < $deadline) {
          if (!$this->processAlive($this->launchedPid)) {
              return $this->startupFailure('A llama.cpp folyamat kilépett a modell betöltése közben. A részletek a llama.cpp naplóban találhatók.');
          }
          $runningModel = $this->getDirectModel();
          if ($runningModel === $modelId) {
              $this->modelId = $modelId;
              return true;
          }
          usleep($this->startupPollMs * 1000);
      }
      return $this->startupFailure('A modell betöltése túllépte a '.$this->startupTimeout.' másodperces várakozási időt. A folyamat még futhat; ellenőrizd a llama.cpp naplót.');
  }	
	
	private function getDirectModel(): ?string
	{
		try {
			$response = $this->request('GET', '/v1/models');

			return $response['data'][0]['id'] ?? null;
		} catch (\Throwable) {
			return null;
		}
	}	
	
private function waitForRouter(): bool
{
    $deadline = microtime(true) + $this->startupTimeout;

    while (microtime(true) < $deadline) {
        if (!$this->processAlive($this->launchedPid)) {
            return $this->startupFailure(
                LANG['LLAMA_ROUTER_EXITED']
            );
        }

        try {
            $response = $this->models();

            if (isset($response['data']) && is_array($response['data'])) {
                return true;
            }
        } catch (\Throwable) {
            // A router még indulhat.
        }

        usleep($this->startupPollMs * 1000);
    }

    return $this->startupFailure(
        sprintf(LANG['LLAMA_ROUTER_TIMEOUT'], $this->startupTimeout)
    );
}

private function startRouterServer(): bool
{
    if ($this->directPortOccupied()) {
        throw new RuntimeException(
            LANG['LLAMA_ROUTER_PORT_BUSY']
        );
    }

    if ($this->binary === '' ||
        !is_file($this->binary) ||
        !is_executable($this->binary)) {
        throw new RuntimeException(
            sprintf(LANG['LLAMA_ROUTER_BINARY_INVALID'], $this->binary)
        );
    }

    if ($this->modelDir === '' || !is_dir($this->modelDir) ||
        !is_readable($this->modelDir)) {
        throw new RuntimeException(
            sprintf(LANG['LLAMA_ROUTER_MODEL_DIR_INVALID'], $this->modelDir)
        );
    }

    $presetFile = dirname($this->pidFile) . '/llama-router.ini';

    (new \mara\core\LlamaRouterPreset())->generate(
        $presetFile,
        $this->modelDir
    );

    clearstatcache(true, $this->logFile);
    $this->startupLogOffset = is_file($this->logFile)
        ? (int)filesize($this->logFile) : 0;

    $command = [
        $this->binary,
        '--host', '127.0.0.1',
        '--port', (string)parse_url($this->baseUrl, PHP_URL_PORT),
        '--models-dir', $this->modelDir,
        '--models-preset', $presetFile,
        '--models-max', '1',
        '--no-models-autoload',
    ];

    $shellCommand = implode(' ', array_map('escapeshellarg', $command));

    $launcher = [
        '/bin/sh',
        '-c',
        $shellCommand
            . ' >> ' . escapeshellarg($this->logFile)
            . ' 2>&1 < /dev/null & echo $!'
    ];

    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $this->logFile, 'a'],
    ];

    $process = proc_open(
        $launcher,
        $descriptors,
        $pipes,
        dirname($this->binary, 3)
    );

    if (!is_resource($process)) {
        return $this->startupFailure(
            LANG['LLAMA_ROUTER_START_FAILED']
        );
    }

    $pid = (int)trim(stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    proc_close($process);

    if ($pid <= 0) {
        return $this->startupFailure(
            LANG['LLAMA_ROUTER_PROCESS_FAILED']
        );
    }

    $this->launchedPid = $pid;

    if (@file_put_contents($this->pidFile, (string)$pid, LOCK_EX) === false) {
        if ($this->processAlive($pid) && function_exists('posix_kill')) {
            @posix_kill($pid, 15);
        }
        return $this->startupFailure(
            LANG['LLAMA_ROUTER_PID_FAILED']
        );
    }

    return $this->waitForRouter();
}

private function startDirectServer(
    string $baseModel,
    string $mmproj = '',
    array $parameters = []
): bool
{
    if ($this->directPortOccupied()) {
        throw new RuntimeException('A llama.cpp portja még foglalt; nem indítok második szervert.');
    }
    clearstatcache(true, $this->logFile);
    $this->startupLogOffset = is_file($this->logFile) ? (int)filesize($this->logFile) : 0;
    $modelId   = pathinfo($baseModel, PATHINFO_FILENAME);
    $modelFile = $this->modelDir . '/' . $baseModel;

    if (
        $this->binary === '' ||
        !is_file($this->binary) ||
        !is_executable($this->binary)
    ) {
        throw new RuntimeException(
            "A llama-server nem futtatható: {$this->binary}"
        );
    }

    if (!is_file($modelFile) || !is_readable($modelFile)) {
        throw new RuntimeException(
            "A modell nem olvasható: {$modelFile}"
        );
    }

    $mmprojFile = null;

    if ($mmproj !== '') {
        $mmprojFile = $this->modelDir . '/' . $mmproj;

        if (!is_file($mmprojFile) || !is_readable($mmprojFile)) {
            throw new RuntimeException(
                "Az mmproj nem olvasható: {$mmprojFile}"
            );
        }
    }    

    $runtime = $this->runtimeOptions($parameters);

    $command = [
        $this->binary,
        '--host', '127.0.0.1',
        '--port', (string) parse_url($this->baseUrl, PHP_URL_PORT),
        '--alias', $modelId,
        '--model', $modelFile,
        '--n-gpu-layers', (string) $runtime['gpu_layers'],
        '--parallel', (string) $this->parallel,
        '--ctx-size', (string)(max(512, (int)($parameters['num_ctx'] ?? 4096)) * max(1, $this->parallel)),
        '--batch-size', (string) $runtime['batch_size'],
        '--ubatch-size', (string) min($runtime['ubatch_size'], $runtime['batch_size']),
        '--flash-attn', $runtime['flash_attn'],
        '--cache-type-k', $runtime['cache_type_k'],
        '--cache-type-v', $runtime['cache_type_v'],
        '--chat-template-kwargs', json_encode([
            'enable_thinking' => filter_var($parameters['think'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ], JSON_THROW_ON_ERROR),
    ];

    // CPU-szálak: -1 esetén a llama.cpp automatikus értéke.
    if ($runtime['threads'] > 0) {
        $command[] = '--threads';
        $command[] = (string)$runtime['threads'];
    }

    if ($runtime['threads_batch'] > 0) {
        $command[] = '--threads-batch';
        $command[] = (string)$runtime['threads_batch'];
    }

    // CPU/MoE offload: teljes vagy részleges MoE, de nem mindkettő.
    if ($runtime['cpu_moe']) {
        $command[] = '--cpu-moe';
    } elseif ($runtime['n_cpu_moe'] > 0) {
        $command[] = '--n-cpu-moe';
        $command[] = (string)$runtime['n_cpu_moe'];
    }

    if ($runtime['n_cpu_ffn'] > 0) {
        $command[] = '--n-cpu-ffn';
        $command[] = (string)$runtime['n_cpu_ffn'];
    }

    if ($mmprojFile !== null) {
        $command[] = '--mmproj';
        $command[] = $mmprojFile;
    }    

    /*
     * A llama-server nem lehet közvetlenül a PHP-FPM worker gyereke,
     * különben leállítás után zombie process maradhat.
     *
     * Ezért egy rövid életű shell indítja háttérben.
     */
    $shellCommand = implode(
        ' ',
        array_map('escapeshellarg', $command)
    );

    $launcher = [
        '/bin/sh',
        '-c',
        $shellCommand
            . ' >> ' . escapeshellarg($this->logFile)
            . ' 2>&1 < /dev/null & echo $!'
    ];

    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $this->logFile, 'a'],
    ];

    $process = proc_open(
        $launcher,
        $descriptors,
        $pipes,
        dirname($this->binary, 3)
    );

    if (!is_resource($process)) {
        return false;
    }

    /*
     * A shell az elindított llama-server PID-jét írja stdout-ra.
     */
    $pid = (int) trim(
        stream_get_contents($pipes[1])
    );

    fclose($pipes[1]);

    /*
     * A launcher shellt itt szándékosan lezárjuk és reapeljük.
     * Így maga a launcher biztosan nem marad zombie.
     */
    proc_close($process);

    if ($pid <= 0) {
        return $this->startupFailure('Nem sikerült elindítani a llama.cpp folyamatot.');
    }
    $this->launchedPid = $pid;

    if (@file_put_contents($this->pidFile, (string)$pid, LOCK_EX) === false) {
        if ($this->processAlive($pid)) @posix_kill($pid, 15);
        return $this->startupFailure('A llama.cpp PID-fájlja nem írható. Ellenőrizd a fájl jogosultságait.');
    }

    return true;
}

  private function stopDirectServer(): bool
  {
      clearstatcache(true, $this->pidFile);
      $pid = (int)trim((string)@file_get_contents($this->pidFile));

      if ($pid <= 0 || !$this->processAlive($pid)) {
          if ($this->directPortOccupied()) {
              $this->startupError = 'A llama.cpp portja foglalt, de nincs hozzá érvényes PID-fájl. A futó szervert előbb azonosítani kell.';
              return false;
          }
          if (is_file($this->pidFile) && !@unlink($this->pidFile)) {
              $this->startupError = 'A llama.cpp PID-fájlja nem törölhető.';
              return false;
          }
          $this->modelId = null;
          return true;
      }

      if ($this->managedProcessMode($pid) === null) {
          $this->startupError = 'A PID-fájl nem a Mara által kezelhető llama-server folyamatra mutat.';
          return false;
      }
      if (!function_exists('posix_kill') || !@posix_kill($pid, 15)) {
          $this->startupError = 'A llama.cpp folyamat nem állítható le.';
          return false;
      }

      $deadline = microtime(true) + $this->startupTimeout;
      while (microtime(true) < $deadline) {
          if (!$this->processAlive($pid) && !$this->directPortOccupied()) {
              if ((int)@file_get_contents($this->pidFile) === $pid &&
                  !@unlink($this->pidFile)) {
                  $this->startupError = 'A llama.cpp PID-fájlja nem törölhető.';
                  return false;
              }
              $this->modelId = null;
              return true;
          }
          usleep($this->startupPollMs * 1000);
      }
      $this->startupError = 'A llama.cpp leállítása túllépte a várakozási időt; a PID-fájlt megtartottam.';
      return false;
  }

  private function isDirectServerRunning(): bool
  {
      try {
          $this->request('GET', '/health');
          return true;
      } catch (\Throwable) {
          return false;
      }
  }

	public function loadModel(string $baseModel): bool
	{
		$model = $this->resolveModel($baseModel);

		if ($model === null) {
			throw new RuntimeException(
				"A modell nem található: {$baseModel}"
			);
		}

		if (($model['status']['value'] ?? null) === 'loaded') {
			return true;
		}

		$this->request(
			'POST',
			'/models/load',
			[
				'model' => $model['id'],
			]
		);

    $maxAttempts = (int)ceil(
        ($this->startupTimeout * 1000) / $this->startupPollMs
    );

    for ($i = 0; $i < $maxAttempts; $i++) {
        usleep($this->startupPollMs * 1000);

        $model = $this->resolveModel($baseModel);

        if ($model === null) {
            return false;
        }

        $status = $model['status']['value'] ?? null;

        if ($status === 'loaded') {
            return true;
        }

        if ($status !== 'loading' && $status !== 'unloaded') {
            return false;
        }
    }

		return false;
	}	
	
}

?>