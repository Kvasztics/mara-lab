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
    $this->startupTimeout = (int)($config['startup_timeout'] ?? 60);
    $this->startupPollMs  = (int)($config['startup_poll_ms'] ?? 250);
    $this->gpuLayers      = (int)($config['gpu_layers'] ?? 0);
    $this->parallel       = (int)($config['parallel'] ?? 1);        
	}

    public function stop(): bool
    {
        $stopped = match ($this->mode) {
            'direct' => $this->stopDirectServer(),
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

    try
      {
        $props  = $this->request('GET', '/props');
        $models = $this->request('GET', '/models');
      }
    catch (\Throwable)
      {
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
            $caps['supports_preserve_reasoning'] ?? false
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

        $payload = array_merge([
            'model'    => $this->modelId,
            'messages' => $messages,
        ], $options);

        if (!empty($tools))
          {
            $payload['tools'] = $tools;
          }

        /*
        * Thinking kezelése.
        *
        * Csak akkor küldjük az enable_thinking kapcsolót,
        * ha az aktuális modell/template támogatja.
        * Az érték a Mara modell konfigurációjából érkezik.
        */
        $capabilities = $this->capabilities();

        if (
            ($capabilities['thinking'] ?? false) &&
            $this->model !== null
        ) {
            $payload['chat_template_kwargs'] = [
                'enable_thinking' => (bool)($this->model['thinking'] ?? false),
            ];
        }

        $response = $this->request(
            'POST',
            '/v1/chat/completions',
            $payload
        );

        $message = $response['choices'][0]['message'] ?? [];

error_log(
    'LLAMA RESPONSE MESSAGE: '.json_encode(
        $response['choices'][0]['message'] ?? null,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    )
);

        return new ProviderResponse(
            content: $message['content'] ?? '',
            model: $response['model'] ?? '',
            finishReason: $response['choices'][0]['finish_reason'] ?? null,
            usage: $response['usage'] ?? [],
            meta: [
                'id'      => $response['id'] ?? null,
                'timings' => $response['timings'] ?? [],
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
        $curl = curl_init($this->baseUrl . $endpoint);

        if ($curl === false) {
            throw new RuntimeException(
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
            CURLOPT_TIMEOUT        => 120,
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

            throw new RuntimeException(
                'Provider kapcsolat hiba: ' . $error
            );
        }

        $status = curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($curl);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(
                "Provider HTTP hiba: {$status}"
            );
        }

        return json_decode(
            $response,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
	
    public function prepare(array $model): bool
    {
        $this->model = null;

        $baseModel = (string)($model['basemodel'] ?? '');
        $mmproj    = (string)($model['mmproj'] ?? '');

        if ($baseModel === '') {
            $this->modelId = null;
            return false;
        }

        $prepared = match ($this->mode) {
            'direct' => $this->prepareDirect($baseModel, $mmproj),
            'router' => $this->prepareRouter($baseModel),
            default  => false,
        };

        if (!$prepared) {
            return false;
        }

        $this->model = $model;

        return true;
    }

	private function prepareRouter(string $baseModel): bool
	{
		if (!$this->loadModel($baseModel)) {
			$this->modelId = null;
			return false;
		}

		$model = $this->resolveModel($baseModel);

		if ($model === null) {
			$this->modelId = null;
			return false;
		}

		$this->modelId = $model['id'];

		return true;
	}

private function prepareDirect(
    string $baseModel,
    string $mmproj = ''
): bool
{
      $modelId = pathinfo($baseModel, PATHINFO_FILENAME);

      // Már fut, és pontosan a kívánt modell van betöltve.
      $runningModel = $this->getDirectModel();

      if ($runningModel === $modelId) {
          $this->modelId = $modelId;
          return true;
      }


      if ($runningModel !== null) {
          if (!$this->stopDirectServer()) {
              $this->modelId = null;
              return false;
          }
      }

      // Nem fut llama-server: elindítjuk a kívánt modellel.
      if (!$this->startDirectServer($baseModel, $mmproj)) {
          $this->modelId = null;
          return false;
      }

      $maxAttempts = (int)ceil(
          ($this->startupTimeout * 1000) / $this->startupPollMs
      );

      for ($i = 0; $i < $maxAttempts; $i++) {
          usleep($this->startupPollMs * 1000);

          $runningModel = $this->getDirectModel();

          if ($runningModel === $modelId) {
              $this->modelId = $modelId;
              return true;
          }
      }

      $this->modelId = null;
      return false;
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
	
private function startDirectServer(
    string $baseModel,
    string $mmproj = ''
): bool
{
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

    $command = [
        $this->binary,
        '--host', '127.0.0.1',
        '--port', (string) parse_url($this->baseUrl, PHP_URL_PORT),
        '--alias', $modelId,
        '--model', $modelFile,
        '--n-gpu-layers', (string) $this->gpuLayers,
        '--parallel', (string) $this->parallel,
    ];

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
        return false;
    }

    file_put_contents(
        $this->pidFile,
        (string) $pid
    );

    return true;
}

  private function stopDirectServer(): bool
  {
      if (!is_file($this->pidFile)) {
          return true;
      }

      $pid = (int)trim((string)file_get_contents($this->pidFile));

      if ($pid <= 0) {
          @unlink($this->pidFile);
          return true;
      }

      // SIGTERM
      if (!posix_kill($pid, 15)) {
          return false;
      }

      // Megvárjuk, amíg a llama-server ténylegesen eltűnik.
      $maxAttempts = (int)ceil(
          ($this->startupTimeout * 1000) / $this->startupPollMs
      );

      for ($i = 0; $i < $maxAttempts; $i++) {
          usleep($this->startupPollMs * 1000);

          if (!$this->isDirectServerRunning()) {
              @unlink($this->pidFile);
              $this->modelId = null;
              return true;
          }
      }

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
