<?php
namespace mara\core\ajax;
/*------------------------------------------------------------------------------
** File:        model_ajax.class.php
** Class:       model_ajax
** Description: model ajax calls
** Version:     1.1
** Updated:     2026-09-28
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use mara\core\User;
use mara\core\App;
use mara\core\provider\ProviderManager;
use mara\core\Models;
use mara\core\Chat;

class model_ajax
{
  private Models   $MOD;
  private Chat     $CHAT;

/**
 * Construct
 * @access public
 * @return void
 */
  public function __construct()
    {
      $this->MOD  = new Models();
      $this->CHAT = new Chat();
    }
/**
 * Change active model
 *
 * @return void
 */
  public function changemodel(): void
  {
    User::loggedIn();
    // Direct startup and shutdown are bounded; allow PHP enough time to return JSON.
    @set_time_limit(300);
    header('Content-Type: application/json; charset=utf-8');
    try {
        $modelId = (int)($_POST['id'] ?? 0);
        if ($modelId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Érvénytelen modellazonosító.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $model = $this->MOD->getById($modelId);
        if ($model === null) {
            echo json_encode(['success' => false, 'error' => 'A kiválasztott modell nem található.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!User::isAdmin() && (int)$model['user_id'] !== 0 && (int)$model['user_id'] !== (int)User::id()) {
            echo json_encode(['success' => false, 'error' => 'Nincs jogosultságod ehhez a modellhez.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $providers = new ProviderManager();
        if (!$providers->activate($model)) {
            echo json_encode(['success' => false, 'error' => $providers->getActivationError() ?: 'A provider aktiválása sikertelen.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        // Update session state only after successful provider activation.
        $this->MOD->setActiveModel($modelId);
        App::set('model', $model);
        $_SESSION['chat_id'] = 0;
        $titles = $this->CHAT->chat_titles($modelId);
        $mdata = $this->MOD->getSelectModelList();
        echo json_encode([
            'success' => true, 'model_id' => $modelId,
            'titles' => $titles, 'models' => $mdata['models'], 'image' => $mdata['image']
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $error) {
        error_log('Mara model change: '.$error->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Szerverhiba történt a modellváltáskor. Ellenőrizd a PHP szervernaplót.'], JSON_UNESCAPED_UNICODE);
    }
  }

/**
 * Get selectable base models for the chosen provider.
 *
 * @return void
 */
public function providermodels(): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    $provider = trim((string)($_POST['provider'] ?? ''));

    if ($provider === '')
      {
        echo json_encode(['success' => false]);
        return;
      }

    try
      {
        $providers = new ProviderManager();
        $activeModel = App::get('model');

        $models = $providers->getModelOptions(
            $provider,
            is_array($activeModel)
                ? $activeModel
                : null
        );

        echo json_encode([
          'success' => true,
          'models'  => $models
        ], JSON_UNESCAPED_UNICODE);
      }
    catch (\Throwable $exception)
      {
        error_log(
            'Provider model list error: '
            . $exception->getMessage()
        );

        echo json_encode([
          'success' => false
        ]);
      }
  }

/**
 * Get metadata for one selectable base model.
 *
 * @return void
 */
public function modelinfo(): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    $provider = trim((string)($_POST['provider'] ?? ''));
    $baseModel = trim((string)($_POST['basemodel'] ?? ''));

    if ($provider === '' || $baseModel === '')
      {
        echo json_encode(['success' => false]);
        return;
      }

    try
      {
        $providers = new ProviderManager();
        $activeModel = App::get('model');

        $modelinfo = $providers->getModelInfo(
            $provider,
            $baseModel,
            is_array($activeModel)
                ? $activeModel
                : null
        );

        echo json_encode([
          'success' => true,
          'modelinfo' => $modelinfo
        ], JSON_UNESCAPED_UNICODE);
      }
    catch (\Throwable $exception)
      {
        error_log(
            'Provider model info error: '
            . $exception->getMessage()
        );

        echo json_encode(['success' => false]);
      }
  }


}
?>
