<?php

declare(strict_types=1);

namespace mara\core\provider;

use mara\core\App;

final class ProviderManager
{
  private array $providers = [];
  private string $activationError = '';

  public function getActivationError(): string
  {
      return $this->activationError;
  }

  private function activationFailed(string $message): bool
  {
      $this->activationError = $message;
      error_log('Mara model activation: '.$message);
      return false;
  }

  public function getProvider(string $provider): ProviderInterface
  {
      if (!isset($this->providers[$provider])) {
          $this->providers[$provider] = ProviderFactory::create(
              $provider,
              App::get("provider.{$provider}", [])
          );
      }

      return $this->providers[$provider];
  }

  public function activate(array $model): bool
  {
      $this->activationError = '';
      $stage = 'A provider beállításainak ellenőrzése';
      try {
          $providerName = trim((string)($model['provider'] ?? ''));
          $baseModel = trim((string)($model['basemodel'] ?? ''));
          if ($providerName === '' || $baseModel === '') {
              return $this->activationFailed('A modell provider vagy alapmodell mezője üres.');
          }
          $enabledProviders = $this->getEnabledProviders();
          if (!in_array($providerName, $enabledProviders, true)) {
              return $this->activationFailed('A modell providere ('.$providerName.') nincs engedélyezve a system.providers beállításban, vagy nincs a támogatott PROVIDERS listában.');
          }
          $multiProvider = (bool)App::get('system.multi_provider', false);
          if (!$multiProvider) {
              foreach ($enabledProviders as $name) {
                  if ($name === $providerName) continue;
                  $stage = 'A másik provider ellenőrzése/leállítása ('.$name.')';
                  $provider = $this->getProvider($name);
                  if ($provider->isAvailable() && !$provider->stop()) {
                      return $this->activationFailed('A másik provider ('.$name.') leállítása sikertelen.');
                  }
              }
          }
          $stage = 'A kiválasztott modell betöltése ('.$providerName.')';
          if (!$this->getProvider($providerName)->prepare($model)) {
              return $this->activationFailed('A kiválasztott modell betöltése sikertelen ('.$providerName.'). Ellenőrizd a provider címét, indítási beállításait és naplóját.');
          }
          return true;
      } catch (\Throwable $error) {
          error_log('Mara model activation exception: '.$error->getMessage());
          return $this->activationFailed($stage.' közben szerverhiba történt. A részletek a PHP szervernaplóban találhatók.');
      }
  }

/**
 * Get selectable base models for a provider without changing the
 * active chat model.
 *
 * When the active model uses llama.cpp and Ollama must be queried,
 * llama-server is temporarily stopped and restored afterwards.
 *
 * @param string $provider
 * @param array|null $activeModel
 * @return array
 */
  public function getModelOptions(
      string $provider,
      ?array $activeModel = null
  ): array
    {
      if (!in_array($provider, $this->getEnabledProviders(), true))
        {
          throw new \RuntimeException('Unknown or disabled provider.');
        }

      return match ($provider) {
          'ollama'   => $this->getOllamaModelOptions($activeModel),
          'llamacpp' => $this->getLlamaCppModelOptions(),
          default    => throw new \RuntimeException(
              'Model listing is not implemented for this provider.'
          )
      };
    }

/**
 * Get metadata for one selectable base model.
 *
 * @param string $provider
 * @param string $baseModel
 * @param array|null $activeModel
 * @return array
 */
  public function getModelInfo(
      string $provider,
      string $baseModel,
      ?array $activeModel = null
  ): array
    {
      if (!in_array($provider, $this->getEnabledProviders(), true))
        {
          throw new \RuntimeException('Unknown or disabled provider.');
        }

      if ($baseModel === '')
        {
          throw new \RuntimeException('Base model is required.');
        }

      return match ($provider) {
          'ollama' => $this->withOllama(
              static fn(OllamaProvider $ollama): array => array_merge(
                  $ollama->capabilities($baseModel),
                  ['capabilities_known' => true]
              ),
              $activeModel
          ),
          'llamacpp' => $this->getLlamaCppModelInfo($baseModel),
          default => throw new \RuntimeException(
              'Model metadata is not implemented for this provider.'
          )
      };
    }

/**
 * Read available GGUF files from the llama.cpp model directory.
 *
 * @return array
 */
  private function getLlamaCppModelOptions(): array
    {
      $modelDir = trim((string)App::get(
          'llamacpp.model_dir',
          ''
      ));

      if ($modelDir === '')
        {
          $config = App::get('provider.llamacpp', []);

          if (is_array($config))
            {
              $modelDir = trim((string)(
                  $config['model_dir'] ?? ''
              ));
            }
        }

      $modelDir = rtrim($modelDir, '/');

      if ($modelDir === '' || !is_dir($modelDir))
        {
          throw new \RuntimeException(
              'Llama.cpp model directory is not available.'
          );
        }

      $files = glob($modelDir.'/*.gguf') ?: [];

      natcasesort($files);

      return array_values(array_map(
          'basename',
          $files
      ));
    }

/**
 * Read GGUF metadata without starting llama-server.
 *
 * @param string $baseModel
 * @return array
 */
  private function getLlamaCppModelInfo(string $baseModel): array
    {
      $provider = $this->getProvider('llamacpp');

      if (!$provider instanceof LlamaCppProvider)
        {
          throw new \RuntimeException('Invalid llama.cpp provider.');
        }

      return $provider->modelInfo($baseModel);
    }

/**
 * Start Ollama when needed and list its installed models.
 *
 * @param array|null $activeModel
 * @return array
 */
  private function getOllamaModelOptions(?array $activeModel): array
    {
      return $this->withOllama(
          static function (OllamaProvider $ollama): array
            {
              $models = [];

              foreach ($ollama->models() as $model)
                {
                  $name = trim((string)($model['name'] ?? ''));

                  if ($name !== '')
                    {
                      $models[] = $name;
                    }
                }

              return array_values(array_unique($models));
            },
          $activeModel
      );
    }

/**
 * Execute an Ollama metadata request without leaving an active llama model
 * stopped. If Ollama is already available, llama is left untouched.
 *
 * @param callable $callback
 * @param array|null $activeModel
 * @return mixed
 */
  private function withOllama(
      callable $callback,
      ?array $activeModel
  ): mixed
    {
      $ollama = $this->getProvider('ollama');

      if (!$ollama instanceof OllamaProvider)
        {
          throw new \RuntimeException('Invalid Ollama provider.');
        }

      $llama = null;
      $restoreLlama = false;

      try
        {
          if (!$ollama->isAvailable())
            {
              if ((string)($activeModel['provider'] ?? '') === 'llamacpp')
                {
                  $llama = $this->getProvider('llamacpp');

                  if ($llama->isAvailable())
                    {
                      if (!$llama->stop())
                        {
                          throw new \RuntimeException(
                              'Active llama.cpp server could not be stopped.'
                          );
                        }

                      $restoreLlama = true;
                    }
                }

              if (!$ollama->start())
                {
                  throw new \RuntimeException(
                      'Ollama service could not be started.'
                  );
                }
            }

          return $callback($ollama);
        }
      finally
        {
          if (
              $restoreLlama &&
              is_array($activeModel) &&
              $llama !== null &&
              !$llama->prepare($activeModel)
          )
            {
              throw new \RuntimeException(
                  'Active llama.cpp model could not be restored.'
              );
            }
        }
    }

  public function isAvailable(string $provider): bool
    {
      return $this->getProvider($provider)->isAvailable();
    }

  public function getEnabledProviders(): array
  {
      $configured = App::get('system.providers', '');

      if (!is_string($configured) || trim($configured) === '') {
          return [];
      }

      $providers = array_map(
          'trim',
          explode(',', $configured)
      );

      return array_values(
          array_intersect($providers, PROVIDERS)
      );
  }    
}

?>
