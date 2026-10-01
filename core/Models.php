<?php
declare(strict_types=1);
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        Models.class.php
** Class:       Models
** Description: Manage models data
** Version:     1.1
** Updated:     2026-09-27
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use mara\database\mModels;
use mara\database\mPsyche;
use mara\core\Template;

final class Models
{
    private mModels $repository;
    private mPsyche $psyche;
    private Voices  $voices;
/**
 * Construct
 * @access public
 * @return void
 */
public function __construct()
  {
    $this->repository = new mModels();
    $this->psyche     = new mPsyche();
    $this->voices     = new Voices();
  }
/**
 * Get model data by ID
 * @access public
 * @param int $id
 * @return array
 */
public function getById(int $id): ?array
  {
    $model = $this->repository->getById($id);

    if ($model === null)
      {
        return null;
      }

    $model = $this->normalize($model);

    /*
     * Voice
     */
    $model['voice'] = null;

    if ((int)$model['voice_id'] > 0)
      {
        $model['voice'] = $this->voices->getById(
          (int)$model['voice_id']
        );
      }

    /*
     * Psyche
     */
    $records = $this->psyche->getByModelId($id);

    $model['psyche_data'] = null;
    $model['past']   = [];

    if (!empty($records))
      {
        $model['psyche_data'] = array_shift($records);

        foreach ($records as $record)
          {
            $model['past'][] = [
              'id'     => (int)$record['id'],
              'modify' => $record['prompt_modify']
            ];
          }
      }

    return $model;
  }  
/**
 * Get active model
 * @access public
 * @param array $providers
 * @return array|null
 */
public function getActiveModel(array $providers): ?array
  {
    $modelId = (int)($_SESSION['model_id'] ?? 0);

    // 1. Van sessionben modell.
    if ($modelId > 0)
      {
        $model = $this->getById($modelId);

        if ($model !== null)
          {
            return $model;
          }

        // A sessionben lévő modell már nem létezik.
        unset($_SESSION['model_id']);
      }

    // 2. Nincs aktív modell:
    // először saját modellt keresünk.
    $userId = User::id();

    foreach ($providers as $provider)
      {
        $model = $this->getFirstUserByProvider(
          $provider,
          $userId
        );

        if ($model !== null)
          {
            return $model;
          }
      }

    // 3. Nincs saját modell:
    // keresünk egy publikus modellt.
    foreach ($providers as $provider)
      {
        $model = $this->getFirstPublicByProvider($provider);

        if ($model !== null)
          {
            return $model;
          }
      }

    // 4. Egyáltalán nincs modell.
    return null;
  }
/**
 * Set active model
 * @access public
 * @param int $modelId
 * @return void
 */
public function setActiveModel(int $modelId): void
  {
    $_SESSION['model_id'] = $modelId;
  }
/**
 * Get first user model by provider.
 * @access public
 * @param string $provider
 * @param int $userId
 * @return array|null
 */
public function getFirstUserByProvider(string $provider, int $userId): ?array
  {
    return $this->normalize(
      $this->repository->getFirstUserByProvider($provider, $userId)
    );
  }  
/**
 * Get first model
 * @access public
 * @param string $provider
 * @return array
 */
public function getFirstPublicByProvider(string $provider): ?array
  {
    return $this->normalize(
        $this->repository->getFirstPublicByProvider($provider)
    );
  }
/**
 * Get models for editing
 * @access public
 * @return string
 */
public function getEditingModelList(): string
  {
    $html    = '';
    $userId  = User::id();
    $models  = $this->repository->getAll($userId, User::isAdmin());
    $modelId = (int)($_SESSION['model_id'] ?? 0);
    foreach ($models as $model) 
      {
        $model = $this->normalize($model);
        if ($model['user_id'] == $userId) 
          {
            $model['info']       = LANG['PERSONAL'];
            $model['badgeclass'] = 'personal'; 
          } else           
          {
            $model['info']       = LANG['PUBLIC'];
            $model['badgeclass'] = 'public'; 
          }
        if ($model['id'] == $modelId) 
          {
            $model['info']       = LANG['ACTIVE'];
            $model['badgeclass'] = 'active'; 
          }  
        $T = new Template(DIR_TPL.'/models_subpage.tpl.php', $model);
        $html.= $T->fetch();  
      }
    return $html;  
  }
/**
 * Get models to select
 * @access public
 * @return array
 */
public function getSelectModelList(): array
  {
    $html    = '';
    $image   = DIR_HOST.'/assets/img/models/noimage.png';
    $userId  = User::id();
    $models  = $this->repository->getAll($userId, User::isAdmin());
    $modelId = (int)($_SESSION['model_id'] ?? 0);
    foreach ($models as $model) 
      {
        $model = $this->normalize($model);
        $data['value'] = $model['id'];
        $data['title'] = $model['name'];
        if ($modelId == $model['id']) 
          {
            $data['selected'] = ' selected';
            $image            = DIR_HOST.'/assets/img/models/'.$model['image'];
          } else {$data['selected'] = '';}          
        $T = new Template(DIR_TPL.'/option.tpl.php', $data);
        $html.= $T->fetch();  
      }
    return array("models"=>$html, "image"=>$image);  
  }    
/**
 * Normalize parameters
 * @access public
 * @param array $model
 * @return array
 */
private function normalize(?array $model): ?array
  {
    if ($model === null) 
      {
        return null;
      }
    $parameters = $model['parameters'] ?? null;
    if ($parameters === null || trim($parameters) === '') 
      {
        $model['parameters'] = [];
      } else 
      {
        $decoded = json_decode($parameters, true);
        $model['parameters'] = is_array($decoded) ? $decoded : [];
      }

    $modelinfo = $model['modelinfo'] ?? null;
    if ($modelinfo === null || trim($modelinfo) === '')
      {
        $model['modelinfo'] = [];
      }
    else
      {
        $decoded = json_decode($modelinfo, true);
        $model['modelinfo'] = is_array($decoded) ? $decoded : [];
      }

    $ragIds = $model['rag_ids'] ?? null;
    if ($ragIds === null || (is_string($ragIds) && trim($ragIds) === ''))
      {
        $model['rag_ids'] = [];
      }
    elseif (is_array($ragIds))
      {
        $model['rag_ids'] = array_values(array_unique(array_filter(
          array_map('intval', $ragIds),
          static fn(int $id): bool => $id > 0
        )));
      }
    else
      {
        $decoded = json_decode((string)$ragIds, true);
        $model['rag_ids'] = is_array($decoded)
          ? array_values(array_unique(array_filter(
              array_map('intval', $decoded),
              static fn(int $id): bool => $id > 0
            )))
          : [];
      }
    return $model;
  }
/**
 * Save model data.
 *
 * Builds the model parameters from the editor input, normalizes JSON fields
 * and delegates the actual SQL insert/update to mModels.
 *
 * Psyche is saved separately after the model update.
 *
 * @param array $input
 * @return int|false Saved model ID, or false on failure
 */
public function save(array $input): int|false
  {
    $modelId = (int)($input['id'] ?? 0);
    $isNew   = $modelId <= 0;

    $userId = User::id();

    if (!$isNew)
      {
        $model = $this->repository->getById($modelId);

        if ($model === null)
          {
            return false;
          }

        if (!User::isAdmin() && (int)$model['user_id'] !== $userId)
          {
            return false;
          }
      }

    /*
     * Parameters
     */
    $parameters = [
      'temperature'       => (float)($input['temperature'] ?? 0.7),
      'frequency_penalty' => (float)($input['frequency_penalty'] ?? 0),
      'repeat_last_n' => isset($input['repeat_last_auto']) ? -1 : (int)($input['repeat_last_n'] ?? 64),
      'presence_penalty'  => (float)($input['presence_penalty'] ?? 0),
      'repeat_penalty'    => (float)($input['repeat_penalty'] ?? 1.1),
      'top_p'             => (float)($input['top_p'] ?? 0.9),
      'top_k'             => (int)($input['top_k'] ?? 40),
      'min_p'             => (float)($input['min_p'] ?? 0.05),
      'max_tokens'        => isset($input['max_tokens_auto']) ? -1 : (int)($input['max_tokens'] ?? 600),
      'num_ctx'           => (int)($input['num_ctx'] ?? 4096),
      'context_limit'     => max(60,min(95,(int)($input['context_limit'] ?? 75))),

      'capabilities' => [
        'tools'            => isset($input['cap_tools']),
        'web_search'       => isset($input['cap_web_search']),
        'code_interpreter' => isset($input['cap_code_interpreter'])
      ],

      'builtin_tools' => $this->decodeIdOrStringList(
        $input['builtin_tools'] ?? []
      )
    ];

    /*
     * Model information is generated by the provider/model selector and is
     * not mixed into parameters.
     */
    $modelinfo = $this->decodeArray(
      $input['modelinfo'] ?? []
    );

    /*
     * RAG document IDs belong to the model, but not to parameters.
     */
    $ragIds = array_values(array_unique(array_filter(
      array_map(
        'intval',
        $this->decodeArray($input['rag_ids'] ?? [])
      ),
      static fn(int $id): bool => $id > 0
    )));

    $data = [
      'provider'       => trim((string)($input['provider'] ?? '')),
      'basemodel'      => trim((string)($input['basemodel'] ?? '')),
      'mmproj'         => trim((string)($input['mmproj'] ?? '')),
      'image'          => trim((string)($input['image_path'] ?? '')) ?: 'noimage.png',
      'thinking'       => isset($input['param_think']) ? 1 : 0,
      'user_id'        => isset($input['private_model']) ? $userId : 0,
      'voice_id'       => (int)($input['voice_id'] ?? 0),
      'name'           => trim((string)($input['name'] ?? '')),
      'note'           => trim((string)($input['note'] ?? '')),
      'prompt'         => (string)($input['system_prompt'] ?? ''),
      'psyche'         => isset($input['psyche']) ? 1 : 0,
      'rag'            => isset($input['rag']) ? 1 : 0,
      'rag_similarity' => (float)($input['rag_similarity'] ?? 0.35),
      'rag_limit'      => (int)($input['rag_limit'] ?? 3),
      'parameters'     => json_encode($parameters, JSON_UNESCAPED_UNICODE),
      'modelinfo'      => json_encode($modelinfo, JSON_UNESCAPED_UNICODE),
      'rag_ids'        => json_encode($ragIds)
    ];

  if ($isNew)
    {
      $modelId = $this->repository->insert($data);

      if ($modelId <= 0)
        {
          return false;
        }
    }
  elseif (!$this->repository->update($modelId, $data))
    {
      return false;
    }

  /*
  * Psyche
  */
  if (isset($input['psyche']))
    {
      $psychePrompt = (string)($input['psyche_prompt'] ?? '');
      $psycheMemory = (string)($input['psyche_memory'] ?? '');
      $psycheReason = (string)($input['psyche_reason'] ?? '');

      if ($isNew || isset($input['psyche_insert']))
        {
          if (!$this->psyche->insert(
            $modelId,
            $psychePrompt,
            $psycheMemory,
            $psycheReason
          ))
            {
              return false;
            }
        }
      else
        {
          if (!$this->psyche->updateLatest(
            $modelId,
            $psychePrompt,
            $psycheMemory,
            $psycheReason
          ))
            {
              return false;
            }
        }
    }

  return $modelId;
  }
/**
 * Decode JSON or array input.
 *
 * @param mixed $value
 * @return array
 */
private function decodeArray(mixed $value): array
  {
    if (is_array($value))
      {
        return $value;
      }

    if (!is_string($value) || trim($value) === '')
      {
        return [];
      }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
  }
/**
 * Decode a JSON/array list and keep non-empty string values.
 *
 * @param mixed $value
 * @return array
 */
private function decodeIdOrStringList(mixed $value): array
  {
    $values = $this->decodeArray($value);
    $result = [];

    foreach ($values as $item)
      {
        if (!is_scalar($item))
          {
            continue;
          }

        $item = trim((string)$item);

        if ($item !== '')
          {
            $result[] = $item;
          }
      }

    return array_values(array_unique($result));
  }
/**
 * Delete model
 *
 * @param int $modelId
 * @return bool
 */
public function delete(int $modelId): bool
  {
    $model = $this->getById($modelId);

    if ($model === null)
      {
        return false;
      }

    $userId = User::id();

    if (!User::isAdmin() && (int)$model['user_id'] !== $userId)
      {
        return false;
      }

    if (!$this->repository->delete($modelId))
      {
        return false;
      }

    if ((int)($_SESSION['model_id'] ?? 0) === $modelId)
      {
        unset($_SESSION['model_id']);
      }

    return true;
  }  
}
?>
