<?php
declare(strict_types=1);
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        Voices.php
** Class:       Voices
** Description: Manage voice data
** Version:     1.1
** Updated:     2026-09-26
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use mara\database\mVoices;
use mara\core\Template;

final class Voices
{
    private mVoices $repository;
/**
 * Construct
 * @access public
 * @return void
 */
public function __construct()
  {
    $this->repository = new mVoices();
  }
/**
 * Get Voice data by ID
 * @access public
 * @param int $id
 * @return array
 */
public function getById(int $id): ?array
  {
    return $this->normalize(
        $this->repository->get($id)
    );
  }
/**
 * Get voices
 * @access public
 * @param string|null $provider
 * @return array
 */
public function getAll(?string $provider = null): array
  {
    $voices = $this->repository->getAll($provider);

    return array_map(
        fn(array $voice): array => $this->normalize($voice),
        $voices
    );
  }
/**
 * Insert voice
 * @access public
 * @param array $voice
 * @return int
 */
public function insert(array $voice): int
  {
    return $this->repository->insert(
        $this->prepare($voice)
    );
  }
/**
 * Update voice
 * @access public
 * @param int $id
 * @param array $voice
 * @return bool
 */
public function update(int $id, array $voice): bool
  {
    return $this->repository->update(
        $id,
        $this->prepare($voice)
    );
  }
/**
 * Delete voice
 * @access public
 * @param int $id
 * @return bool
 */
public function delete(int $id): bool
  {
    return $this->repository->delete($id);
  }
/**
 * Voices in select
 * @access public
 * @param int $voiceId
 * @param string|null $provider
 * @return string
 */
public function selectVoices($voiceId, ?string $provider = null): string
  {
    $html   = '';
    $voices = $this->repository->getAll($provider);
    foreach ($voices as $voice) 
      {
        $data['value'] = $voice['id'];
        $data['title'] = $voice['name'];
        if ($voiceId == $voice['id']) {$data['selected'] = ' selected';} else {$data['selected'] = '';}
        $T = new Template(DIR_TPL.'/option.tpl.php',$data);
        $html.= $T->fetch();
      }
    return $html;  
  }



/**
 * Prepare voice data for repository
 * @access private
 * @param array $voice
 * @return array
 */
private function prepare(array $voice): array
  {
    $voice['sample'] =
        trim((string)($voice['sample'] ?? '')) ?: null;

    $voice['reftext'] =
        trim((string)($voice['reftext'] ?? '')) ?: null;

    $parameters = $voice['parameters'] ?? [];

    if (isset($parameters['pitch']))
      {
        $parameters['pitch'] =
            (int)$parameters['pitch'];
      }

    if (isset($parameters['speed']))
      {
        $parameters['speed'] =
            (float)$parameters['speed'];
      }

    if (isset($parameters['temperature']))
      {
        $parameters['temperature'] =
            (float)$parameters['temperature'];
      }

    if (isset($parameters['length_scale']))
      {
        $parameters['length_scale'] =
            (float)$parameters['length_scale'];
      }

    if (isset($parameters['noise_w']))
      {
        $parameters['noise_w'] =
            (float)$parameters['noise_w'];
      }   

    $voice['parameters'] =
        empty($parameters)
            ? null
            : json_encode(
                $parameters,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

    return $voice;
  }
/**
 * Normalize parameters
 * @access private
 * @param array|null $voice
 * @return array|null
 */
private function normalize(?array $voice): ?array
  {
    if ($voice === null) 
      {
        return null;
      }
    $parameters = $voice['parameters'] ?? null;
    if ($parameters === null || trim($parameters) === '') 
      {
        $voice['parameters'] = [];
      } else 
      {
        $decoded = json_decode($parameters, true);
        $voice['parameters'] = is_array($decoded) ? $decoded : [];
      }
    return $voice;
  }   
}
?>