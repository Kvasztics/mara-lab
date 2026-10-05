<?php
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        main.class.php
** Class:       main
** Description: main functions
** Version:     1.1
** Updated:     2026-09-24
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use mara\core\User;
use mara\core\App;
use mara\core\Settings;
use mara\core\provider\ProviderFactory;
use mara\core\Voices;
use mara\core\Models;
use mara\core\Rag;
use mara\core\Chat;
use mara\core\voice\VoiceManager;
use mara\core\stt\SttManager;

class main
{
  private Settings $SET;
  private Voices   $VOC;
  private Models   $MOD;
  private Rag      $RAG;
  private Chat     $CHAT;

  use \mara\core\Render;
/**
 * Construct
 * @access public
 * @return void
 */
  public function __construct()
    {
      $this->SET  = new Settings();
      $this->VOC  = new Voices();
      $this->MOD  = new Models();
      $this->RAG  = new Rag();
      $this->CHAT = new Chat();
    }
/**
 * Basic settings for javascript
 * @access public
 * @return void
 */    
public function jssettings()
  {
    $_data['root'] = DIR_ROOT;
    $_data['host'] = DIR_HOST;
    $_data['lang'] = LANG; 
  /*  if (App::get('system')['voice_enabled'] == 1)
      {
        $_data['voice_start'] = App::get('system')['voice_start'];
        $_data['voice']       = App::get('system')['voice'];
      } else
      {
        $_data['voice_start'] = 0;
        $_data['voice']       = '';
      }
    if (App::get('system')['recognition_enabled'] == 1)
      {
        $_data['recognition_enabled'] = App::get('system')['recognition_enabled'];
        $_data['recognition']         = App::get('system')['recognition'];
      } else
      {
        $_data['recognition_enabled'] = 0;
        $_data['recognition']         = '';
      } */     
    echo json_encode($_data);
  }    
/**
 * Generate one speech chunk for the active model voice.
 * The first chunk verifies a remote XTTS server; subsequent chunks skip it.
 */
public function voice($var = []): void
  {
    User::loggedIn();

    $text = trim((string)($_GET['text'] ?? ''));
    $model = App::get('model');

    if ($text === '' || !is_array($model))
      {
        http_response_code(400);
        exit;
      }

    $voice = $model['voice'] ?? null;

    if (!is_array($voice))
      {
        $voiceId = (int)($model['voice_id'] ?? 0);
        $voice = $voiceId > 0
            ? $this->VOC->getById($voiceId)
            : null;
      }

    if (!is_array($voice))
      {
        http_response_code(404);
        exit;
      }

    try
      {
        (new VoiceManager())->speak(
            $voice,
            $text,
            (string)($_GET['verify'] ?? '') === '1'
        );
      }
    catch (\Throwable $e)
      {
        error_log('Voice playback failed: '.$e->getMessage());
        http_response_code(503);
      }

    exit;
  }
/**
 * Transcribe one recorded microphone clip.
 */
public function transcribe($var = []): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
      {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error'   => 'STT_ERROR_UPLOAD'
        ]);
        exit;
      }

    try
      {
        $result = (new SttManager())->transcribe(
            $_FILES['audio_blob'] ?? []
        );

        echo json_encode($result);
      }
    catch (\Throwable $e)
      {
        error_log('STT failed: '.$e->getMessage());

        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error'   => 'STT_ERROR_SERVER'
        ]);
      }

    exit;
  }
/**
 * Render main page
 * @param array $var
 * @return void
 */
public function view($var = []): void
  {
    User::loggedIn();
    if (empty($_SESSION['model_id']))
      {
        $_SESSION['message'] = LANG['MSG_NO_MODEL'];
        header('Location: '.DIR_HOST.'/main/newmodel');
        exit;
      }    
    $mdata              = $this->MOD->getSelectModelList();  
    $pagedata['lang']   = App::get('system.language');    
    $pagedata['stt_provider'] = strtolower(
        (string) App::get('system.stt_provider', '')
    );
    $pagedata['stt_language'] = (string) App::get(
        'stt.whisper.language',
        strtolower((string)$pagedata['lang'])
    );
    $pagedata['models'] = $mdata['models'];
    $pagedata['image']  = $mdata['image'];
    $pagedata['chats']  = (!empty($_SESSION['model_id']) ? $this->CHAT->chat_titles((int)$_SESSION['model_id']) : '');
    $this->show([
        'header'   => DIR_TPL.'/header.tpl.php',
        'page'     => DIR_TPL.'/chat.tpl.php',
        'footer'   => DIR_TPL.'/footer.tpl.php',
        'html'     => false,
        'pagedata' => $pagedata
    ]);
  }
/**
 * Render models page
 * @param array $var
 * @return void
 */
public function models($var = []): void
  {
    User::loggedIn();
    $modelId = (int)($var[0] ?? 0);
    $pagedata['lang']   = App::get('system.language');
    $mdata              = $this->MOD->getSelectModelList();
    $pagedata['models'] = $mdata['models'];
    $pagedata['chats']  = (!empty($_SESSION['model_id']) ? $this->CHAT->chat_titles((int)$_SESSION['model_id']) : '');
    if ($modelId > 0)
      {
        $template = DIR_TPL.'/model.tpl.php';
        $model    = $this->MOD->getById($modelId);
        if ($model === null)
          {
            header('Location: '.DIR_HOST.'/main/models');
            exit;
          }        
        $pagedata = array_merge($pagedata, $model);
        $pagedata['voiceselect'] = $this->VOC->selectVoices($model['voice_id']);
        $pagedata['ragdocuments'] = $this->RAG->getDocuments();
        $pagedata['modelimages'] = $this->gallery();
      } else
      {
        $template = DIR_TPL.'/models.tpl.php';
        $pagedata['smodels'] = $this->MOD->getEditingModelList();
      }  
    $this->show([
        'header'   => DIR_TPL.'/header.tpl.php',
        'page'     => $template,
        'footer'   => DIR_TPL.'/footer.tpl.php',
        'html'     => false,
        'pagedata' => $pagedata
    ]);
  }
/**
 * Create new model page
 * @param array $var
 * @return void
 */
public function newmodel($var = []): void
  {
    User::loggedIn();
    $pagedata['lang']    = App::get('system.language');
    $mdata              = $this->MOD->getSelectModelList();
    $pagedata['models'] = $mdata['models'];
    $pagedata['chats']  = (!empty($_SESSION['model_id']) ? $this->CHAT->chat_titles((int)$_SESSION['model_id']) : '');
    $pagedata['voiceselect'] = $this->VOC->selectVoices(0);
    $pagedata['ragdocuments'] = $this->RAG->getDocuments();
    $pagedata['modelimages'] = $this->gallery();
    $this->show([
        'header'   => DIR_TPL.'/header.tpl.php',
        'page'     => DIR_TPL.'/model.tpl.php',
        'footer'   => DIR_TPL.'/footer.tpl.php',
        'html'     => false,
        'pagedata' => $pagedata
    ]);
  }
/**
 * Save model
 *
 * @return void
 */
public function savemodel(): void
  {
    User::loggedIn();

    $modelId = $this->MOD->save($_POST);

    if ($modelId !== false)
      {
        $_SESSION['message'] = LANG['MSG_SAVE_OK'];
        header('Location: '.DIR_HOST.'/main/models/'.$modelId);
        exit;
      }

    header('Location: '.DIR_HOST.'/main/models');
    exit;
  }
/**
 * Delete model
 *
 * @return void
 */
public function modeldelete(): void
  {
    User::loggedIn();

    $modelId = (int)($_POST['id'] ?? 0);

    $success = false;

    if ($modelId > 0)
      {
        $success = $this->MOD->delete($modelId);
      }

    header('Content-Type: application/json');

    echo json_encode([
      'success' => $success
    ]);
  }     
/**
 * Render settings page
 * @param array $var
 * @return void
 */
public function settings($var = []): void
  {    
    User::loggedIn();    
    $pagedata               = App::get('system');
    $mdata                  = $this->MOD->getSelectModelList();
    $pagedata['models']     = $mdata['models'];
    $pagedata['chats']      = (!empty($_SESSION['model_id']) ? $this->CHAT->chat_titles((int)$_SESSION['model_id']) : '');
    $provider               = App::get('system.provider', '');
    $pagedata['slanguages'] = $this->SET->getLanguages(App::get('system.language'));
    $pagedata['sproviders'] = $this->SET->getProviders($provider); 
    $pagedata['voices']     = $this->VOC->getAll();          

    $providers = [];
    foreach ($this->SET->getProviderNames() as $provider) 
      {
        $providers[$provider] = $this->SET->values($provider);
      }
    $pagedata['providerdata'] = $providers;
    $pagedata['logSources'] = User::isAdmin() ? LogViewer::sources() : [];  
    
    $pagedata['voiceproviders'] = array_filter(
        array_map(
            'trim',
            explode(',', App::get('system.tts_providers', ''))
        )
    );

    $pagedata['tts'] = [
        'provider'  => App::get('system.tts_provider', ''),
        'providers' => App::get('system.tts_providers', ''),
        'xtts'      => App::get('tts.xtts', []),
        'piper'     => App::get('tts.piper', []),
        'espeak'    => App::get('tts.espeak', []),
    ];

    $pagedata['stt'] = [
        'provider'  => App::get('system.stt_provider', ''),
        'providers' => App::get('system.stt_providers', ''),
        'whisper'   => App::get('stt.whisper', []),
    ];

    $this->show([
        'header'   => DIR_TPL.'/header.tpl.php',
        'page'     => DIR_TPL.'/settings.tpl.php',
        'footer'   => DIR_TPL.'/footer.tpl.php',
        'html'     => false,
        'pagedata' => $pagedata
    ]);
  }
/**
 * Save settings data
 * @param array $var
 * @return void
 */
public function logread($var = []): void
  {
    User::loggedIn();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    if (!User::isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Csak adminisztrátornak elérhető.']);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo json_encode(['success' => false, 'error' => 'GET kérés szükséges.']);
        exit;
    }
    try {
        $result = LogViewer::read((string)($_GET['source'] ?? ''));
        echo json_encode(['success' => true] + $result,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (\Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
  }

public function settingssave($var = []): void
  {
    User::loggedIn();
//----- System settings --------------------------------------------------
    $language = trim($_POST['language'] ?? '');
    $provider = trim($_POST['model_provider'] ?? '');
    if ($language !== '') 
      {
        $this->SET->setValue(
            'system',
            'language',
            $language
        );
      }
    if ($provider !== '') 
      {
        $this->SET->setValue(
            'system',
            'provider',
            $provider
        );
      }
//----- Provider settings -----------------------------------------------
    $providerData = json_decode(
        $_POST['provider_data'] ?? '{}',
        true
    );
    if (is_array($providerData)) 
      {
        foreach ($providerData as $provider => $settings) 
          {
            // Csak Mara által támogatott provider
            if (!in_array($provider, PROVIDERS, true)) 
              {
                continue;
              }
            if (!is_array($settings)) 
              {
                continue;
             }
            foreach ($settings as $key => $value) 
              {
                $this->SET->setValue(
                    $provider,
                    (string) $key,
                    trim((string) $value)
                );
              }
          }
      }
//----- Voice settings ---------------------------------------------------
    $ttsData = $_POST['tts_data'] ?? [];
    if (is_array($ttsData)) 
      {
        foreach ($ttsData as $provider => $settings) 
          {
            if (!is_array($settings)) 
              {
                continue;
              }
            foreach ($settings as $key => $value) 
              {
                $this->SET->setValue(
                    $provider,
                    (string) $key,
                    trim((string) $value)
                );
              }
          }
      }
//----- Recognition settings ---------------------------------------------
    $sttProvider = trim($_POST['stt_provider'] ?? '');

    if ($sttProvider !== '')
      {
        $this->SET->setValue(
            'system',
            'stt_provider',
            $sttProvider
        );
      }

    $whisperSettings = [
        'dir'             => $_POST['whisper_dir'] ?? '',
        'host'            => $_POST['whisper_host'] ?? '',
        'port'            => $_POST['whisper_port'] ?? '',
        'model'           => $_POST['whisper_model'] ?? '',
        'language'        => $_POST['whisper_language'] ?? '',
        'temperature'     => $_POST['whisper_temperature'] ?? '',
        'temperature_inc' => $_POST['whisper_temperature_inc'] ?? '',
        'best_of'         => $_POST['whisper_best_of'] ?? '',
        'beam_size'       => $_POST['whisper_beam_size'] ?? '',
        'no_speech_thold' => $_POST['whisper_no_speech_thold'] ?? '',
        'use_context'     => isset($_POST['whisper_use_context']) ? '1' : '0'
    ];

    foreach ($whisperSettings as $key => $value)
      {
        $this->SET->setValue(
            'whisper',
            $key,
            trim((string) $value)
        );
      }
//------------------------------------------------------------------------
    $_SESSION['message'] = LANG['MSG_SAVE_OK'];
    header('Location: '.DIR_HOST.'/main/settings');    
  }
/**
 * Render knowledge page
 * @param array $var
 * @return void
 */
public function rag($var = []): void
  {
    User::loggedIn();
    $ragId = (int)($var[0] ?? 0);
    $pagedata['lang']   = App::get('system.language');
    $mdata              = $this->MOD->getSelectModelList();
    $pagedata['models'] = $mdata['models'];
    $pagedata['chats']  = (!empty($_SESSION['model_id']) ? $this->CHAT->chat_titles((int)$_SESSION['model_id']) : '');
    $pagedata['knowledge'] = $this->RAG->getList();
    $template = DIR_TPL.'/rag.tpl.php';
    $this->show([
        'header'   => DIR_TPL.'/header.tpl.php',
        'page'     => $template,
        'footer'   => DIR_TPL.'/footer.tpl.php',
        'html'     => false,
        'pagedata' => $pagedata
    ]);
  }
/**
 * New Rag data
 * @param array $var
 * @return void
 */
public function ragnew($var = []): void
  {
    User::loggedIn();

    $pagedata['lang']   = App::get('system.language');
    $mdata              = $this->MOD->getSelectModelList();
    $pagedata['models'] = $mdata['models'];
    $pagedata['chats']  = (!empty($_SESSION['model_id']) ? $this->CHAT->chat_titles((int)$_SESSION['model_id']) : '');
    $pagedata['action'] = DIR_HOST.'/main/ragsave';

    $pagedata['id']      = 0;
    $pagedata['title']   = '';
    $pagedata['content'] = '';
    $pagedata['isAdmin'] = User::isAdmin();
    $pagedata['user_id'] = User::id();

    $this->show([
        'header'   => DIR_TPL.'/header.tpl.php',
        'page'     => DIR_TPL.'/rag_settings.tpl.php',
        'footer'   => DIR_TPL.'/footer.tpl.php',
        'html'     => false,
        'pagedata' => $pagedata
    ]);
  }
/**
 * Save Rag data
 *
 * @param array $var
 * @return void
 */
public function ragsave($var = []): void
  {
    User::loggedIn();

    $title   = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));

    /*
     * Normal user can only create own knowledge.
     * Admin can choose public or own.
     */
    if (User::isAdmin())
      {
        $userId = (int)($_POST['user_id'] ?? User::id());

        if ($userId !== 0 && $userId !== User::id())
          {
            $userId = User::id();
          }
      }
    else
      {
        $userId = User::id();
      }

    try
      {
        $this->RAG->add(
            $title,
            $content,
            $userId
        );

        header(
            'Location: '.DIR_HOST.'/main/rag'
        );

        exit;
      }
    catch (\Throwable $e)
      {
        error_log(
            'RAG save error: '.$e->getMessage()
        );

        throw $e;
      }
  }
/**
 * Delete Rag data
 *
 * @param array $var
 * @return void
 */
public function ragdelete($var = []): void
  {
    User::loggedIn();
    $ragId = (int)($_POST['id'] ?? 0);
    $success = $this->RAG->delete($ragId);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success
    ]);
    exit;
  }    
/**
 * Test provider connection
 * @param array $var
 * @return void
 */
public function providertest($var = []): void
  {
    User::loggedIn();
    header('Content-Type: application/json; charset=utf-8');
    $provider = trim($_POST['provider'] ?? '');
    $url      = trim($_POST['url'] ?? '');
    if ($provider === '' || $url === '') 
      {
        echo json_encode([
            'success' => false,
            'message' => LANG['SETTINGS_STOPPED']
        ]);
        return;
      }
    try {
        $config = App::get('provider.' . $provider, []);
        $config['url'] = $url;
        $providerObject = ProviderFactory::create(
            $provider,
            $config
        );
        $available = $providerObject->isAvailable();
        echo json_encode([
            'success' => $available,
            'message' => $available
                ? LANG['SETTINGS_RUNNING']
                : LANG['SETTINGS_STOPPED']
        ]);
    } catch (\Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => LANG['SETTINGS_STOPPED']
        ]);
    }
  }
/**
 * Save voice
 * @param array $var
 * @return void
 */
public function voicesave($var = []): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    try
      {
        $id = (int)($_POST['id'] ?? 0);

        $voice = [
            'provider'   => trim($_POST['provider'] ?? ''),
            'name'       => trim($_POST['name'] ?? ''),
            'sample'     => trim($_POST['sample'] ?? ''),
            'reftext'    => trim($_POST['reftext'] ?? ''),
            'parameters' => $_POST['parameters'] ?? []
        ];

        if (
            $voice['provider'] === '' ||
            $voice['name'] === ''
        )
          {
            throw new \RuntimeException();
          }

        if ($id > 0)
          {
            $success = $this->VOC->update(
                $id,
                $voice
            );

            $voiceId = $id;
          }
        else
          {
            $voiceId = $this->VOC->insert($voice);
            $success = $voiceId > 0;
          }

        echo json_encode([
            'success' => $success,
            'id'      => $voiceId
        ]);
      }
    catch (\Throwable $e)
      {
        echo json_encode([
            'success' => false
        ]);
      }
  } 
/**
 * Delete voice
 * @param array $var
 * @return void
 */
public function voicedelete($var = []): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    try
      {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0)
          {
            throw new \RuntimeException();
          }

        $success = $this->VOC->delete($id);

        echo json_encode([
            'success' => $success
        ]);
      }
    catch (\Throwable $e)
      {
        echo json_encode([
            'success' => false
        ]);
      }
  }  
/**
 * Test tts connection
 * @param array $var
 * @return void
 */
public function ttstest($var = []): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    $provider = trim($_POST['provider'] ?? '');

    try
      {
        switch ($provider)
          {
            /*
             * XTTS
             */
            case 'xtts':
                $messageType = 'server';
                $url = rtrim(
                    trim($_POST['url'] ?? ''),
                    '/'
                );

                if ($url === '')
                  {
                    throw new \RuntimeException();
                  }

                $ch = curl_init(
                    $url . '/openapi.json'
                );

                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT        => 5,
                ]);

                $response = curl_exec($ch);

                $httpCode = curl_getinfo(
                    $ch,
                    CURLINFO_HTTP_CODE
                );

                curl_close($ch);

                if (
                    $response === false ||
                    $httpCode !== 200
                )
                  {
                    throw new \RuntimeException();
                  }

                $data = json_decode(
                    $response,
                    true
                );

                $available =
                    is_array($data) &&
                    isset(
                        $data['paths']['/synthesize']
                    );

                break;


            /*
             * Piper
             */
            case 'piper':
                $messageType = 'connection';
                $binary = trim(
                    $_POST['binary'] ?? ''
                );

                $modelDir = trim(
                    $_POST['model_dir'] ?? ''
                );

                if (
                    $binary === '' ||
                    $modelDir === ''
                )
                  {
                    throw new \RuntimeException();
                  }

                $available =
                    is_file($binary) &&
                    is_executable($binary) &&
                    is_dir($modelDir);

                break;

            /*
             * eSpeak
             */
            case 'espeak':
                $messageType = 'connection';
                $binary = trim(
                    $_POST['binary'] ?? ''
                );

                $voice = trim(
                    $_POST['voice'] ?? ''
                );

                if (
                    $binary === '' ||
                    $voice === ''
                )
                  {
                    throw new \RuntimeException();
                  }

                /*
                 * Ha csak parancsnév van megadva (pl. espeak-ng),
                 * keressük meg a PATH-ban.
                 */
                if (str_contains($binary, '/'))
                  {
                    $binaryPath = $binary;
                  }
                else
                  {
                    $binaryPath = trim(
                        (string) shell_exec(
                            'command -v ' .
                            escapeshellarg($binary)
                        )
                    );
                  }

                if (
                    $binaryPath === '' ||
                    !is_file($binaryPath) ||
                    !is_executable($binaryPath)
                )
                  {
                    $available = false;
                    break;
                  }

                /*
                 * A kiválasztott hangot is ellenőrizzük.
                 */
                $command =
                    escapeshellarg($binaryPath) .
                    ' -v ' .
                    escapeshellarg($voice) .
                    ' --stdout';

                $process = proc_open(
                    $command,
                    [
                        0 => ['pipe', 'r'],
                        1 => ['pipe', 'w'],
                        2 => ['pipe', 'w'],
                    ],
                    $pipes
                );

                if (!is_resource($process))
                  {
                    $available = false;
                    break;
                  }

                fwrite(
                    $pipes[0],
                    'Teszt'
                );

                fclose($pipes[0]);

                $audio = stream_get_contents(
                    $pipes[1]
                );

                fclose($pipes[1]);

                $error = stream_get_contents(
                    $pipes[2]
                );

                fclose($pipes[2]);

                $exitCode = proc_close($process);

                $available =
                    $exitCode === 0 &&
                    $audio !== '';

                break;                

            /*
             * Unknown provider
             */
            default:

                throw new \RuntimeException();
          }


        if ($messageType === 'server')
          {
            $message = $available
                ? LANG['SETTINGS_RUNNING']
                : LANG['SETTINGS_STOPPED'];
          }
        else
          {
            $message = $available
                ? LANG['SETTINGS_CONNECTION_OK']
                : LANG['SETTINGS_CONNECTION_ERROR'];
          }

        echo json_encode([
            'success' => $available,
            'message' => $message
        ]);
      }
    catch (\Throwable $e)
      {
        echo json_encode([
            'success' => false,
            'message' => $provider === 'xtts'
                ? LANG['SETTINGS_STOPPED']
                : LANG['SETTINGS_CONNECTION_ERROR']
        ]);
      }
  }
/**
 * Test stt connection
 * @param array $var
 * @return void
 */
public function stttest($var = []): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    $provider = trim($_POST['provider'] ?? '');

    try
      {
        switch ($provider)
          {
            /*
             * Browser
             */
            case 'browser':
                $messageType = 'availability';
                $available = true;

                break;


            /*
             * Whisper
             */
            case 'whisper':
                $messageType = 'server';
                $host = trim(
                    $_POST['host'] ?? ''
                );

                $port = (int)(
                    $_POST['port'] ?? 0
                );

                if (
                    $host === '' ||
                    $port <= 0
                )
                  {
                    throw new \RuntimeException();
                  }

                $ch = curl_init(
                    'http://' .
                    $host .
                    ':' .
                    $port .
                    '/'
                );

                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT        => 5,
                ]);

                $response = curl_exec($ch);

                $httpCode = curl_getinfo(
                    $ch,
                    CURLINFO_HTTP_CODE
                );

                curl_close($ch);

                $available =
                    $response !== false &&
                    $httpCode === 200 &&
                    str_contains(
                        $response,
                        'Whisper.cpp Server'
                    );

                break;


            /*
             * Unknown provider
             */
            default:

                throw new \RuntimeException();
          }


        if ($messageType === 'server')
          {
            $message = $available
                ? LANG['SETTINGS_RUNNING']
                : LANG['SETTINGS_STOPPED'];
          }
        else
          {
            $message = $available
                ? LANG['SETTINGS_AVAILABLE']
                : LANG['SETTINGS_NOT_AVAILABLE'];
          }

        echo json_encode([
            'success' => $available,
            'message' => $message
        ]);
      }
    catch (\Throwable $e)
      {
        echo json_encode([
            'success' => false,
            'message' => $provider === 'whisper'
                ? LANG['SETTINGS_STOPPED']
                : LANG['SETTINGS_NOT_AVAILABLE']
        ]);
      }
  }
/**
 * Get images to gallery
 * @access public
 * @return string
 */
private function gallery() 
  {
    $html = '';
    $images = $this->images();
    foreach ($images as $image) 
      {
        $T = new Template(DIR_TPL.'/model_gallery_element.tpl.php', array("image"=>$image));
        $html.= $T->fetch();
      }
    return $html;  
  }
/**
 * Get imeges to gallery
 * @access private
 * @return array
 */
private function images() 
  {
    $dirPath = DIR_ROOT.'/public/assets/img/models/';
    $galleryImages = [];
    $files = glob($dirPath . '*.{png,jpg,jpeg,webp}', GLOB_BRACE);
    if ($files) 
      {
        usort($files, function($a, $b) {
          return filemtime($b) - filemtime($a);
        });
        foreach ($files as $file) 
          {
            $filename = basename($file);
            $galleryImages[] = DIR_HOST.'/assets/img/models/' . $filename . '?t=' . filemtime($file);
          }
        return $galleryImages;  
      }
    return array();  
  }      


public function ragtest($var = []): void
  {
    User::loggedIn();

    $result = $this->RAG->search(
        'Milyen színű az ég?',
        [31],
        0.0,
        10
    );

    echo '<pre>';
    print_r($result);
    echo '</pre>';
    exit;
  }  
}
?>
