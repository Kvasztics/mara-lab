<?php

declare(strict_types=1);

use mara\core\App;
use mara\core\Settings;
use mara\core\Models;
use mara\core\User;
use mara\core\provider\ProviderManager;

/*
 * Application paths
 */
define('DIR_ROOT', dirname(__DIR__));
define('DIR_TPL',  DIR_ROOT . '/tpl');

/*
 * Application URL
 */
$protocol = (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== '' &&
    $_SERVER['HTTPS'] !== 'off'
) ? 'https://' : 'http://';

define('DIR_HOST', $protocol . $_SERVER['HTTP_HOST']);

/*
 * Public assets
 */
define('DIR_CSS',  DIR_HOST . '/assets/css');
define('DIR_IMG',  DIR_HOST . '/assets/img');
define('DIR_MIMG', DIR_HOST . '/assets/img/models');
define('DIR_JS',   DIR_HOST . '/assets/js');

/*
 * Settings
 */
$settings = new Settings();

App::init([
    'app' => [
        'name'     => 'Mara Lab',
        'debug'    => true,
    ],

    'system' => $settings->values('system'),    

    'provider' => [
        'llamacpp' => $settings->values('llamacpp'),
        'ollama'   => $settings->values('ollama'),
    ],

    'tts' => [
        'xtts' => $settings->values('xtts'),
        'piper' => $settings->values('piper'),
        'espeak' => $settings->values('espeak'),
    ], 
    
    'stt' => [
        'whisper' => $settings->values('whisper'),
    ],    
]);

/*
 * Language
 */
$language = strtolower(
    (string) App::get('system.language', 'HU')
);

$languageFile = DIR_ROOT . '/public/assets/languages/' . $language . '.ini';

define(
    'LANG',
    is_file($languageFile)
        ? parse_ini_file($languageFile)
        : []
);

/*
 * Active model
 */
if (!empty(User::get()))
  {
    $models = new Models();

    if (empty($_SESSION['model_id']))
      {
        $providers = new ProviderManager();

        $model = $models->getActiveModel(
          $providers->getEnabledProviders()
        );

        if ($model !== null && $providers->activate($model))
          {
            $models->setActiveModel((int)$model['id']);
            App::set('model', $model);
          }
      }
    else
      {
        App::set(
            'model',
            $models->getById((int)$_SESSION['model_id'])
        );
      }
  }
?>