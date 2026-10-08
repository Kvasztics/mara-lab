<?php
declare(strict_types=1);

namespace mara\core;

use mara\core\integration\CharacterCard;
use mara\core\integration\CharacterCardMapper;
use mara\core\integration\CharacterCardPng;
use mara\database\Database;

trait CharacterCards
{
    private function characterCardCsrf(): string
    {
        if (empty($_SESSION['character_card_csrf'])) {
            $_SESSION['character_card_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['character_card_csrf'];
    }

    private function validCharacterCardCsrf(mixed $token): bool
    {
        return is_string($token) &&
            isset($_SESSION['character_card_csrf']) &&
            hash_equals($_SESSION['character_card_csrf'], $token);
    }

    private function characterCardRedirect(string $message = ''): never
    {
        if ($message !== '') {
            $_SESSION['message'] = $message;
        }
        header('Location: ' . DIR_HOST . '/main/models');
        exit;
    }

    private function characterCardImportResult(bool $success, string $value): never
    {
        $ajax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))
            === 'xmlhttprequest';
        if ($ajax) {
            while (ob_get_level() > 0) {
                if (!ob_end_clean()) {
                    throw new \RuntimeException('Cannot clear import response buffer.');
                }
            }
            http_response_code($success ? 200 : 400);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(
                $success
                    ? ['success' => true, 'redirect' => $value]
                    : ['success' => false, 'error' => $value],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            exit;
        }
        if ($success) {
            header('Location: ' . $value);
            exit;
        }
        $this->characterCardRedirect($value);
    }

    public function importcard(array $vars = []): void
    {
        User::loggedIn();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
            !$this->validCharacterCardCsrf($_POST['card_csrf_token'] ?? null)) {
            $this->characterCardImportResult(false, LANG['CARD_ERROR']);
        }

        $portraitPath = null;
        try {
            $upload = $_FILES['character_card'] ?? null;
            if (!is_array($upload) ||
                ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
                !is_string($upload['tmp_name'] ?? null) ||
                !is_uploaded_file($upload['tmp_name'])) {
                throw new \InvalidArgumentException('Invalid character card upload.');
            }

            $size = filesize($upload['tmp_name']);
            if ($size === false || $size < 1 ||
                $size > CharacterCardPng::MAX_BYTES) {
                throw new \InvalidArgumentException('Character card upload exceeds limit.');
            }
            $bytes = file_get_contents($upload['tmp_name']);
            if ($bytes === false) {
                throw new \RuntimeException('Cannot read character card upload.');
            }

            $isPng = str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
            $card = $isPng ? CharacterCardPng::read($bytes) : CharacterCard::decode($bytes);
            $model = CharacterCardMapper::toModel($card, User::id());
            $provider = (string)App::get('system.provider', '');
            $model['provider'] = in_array($provider, PROVIDERS, true)
                ? $provider : (string)(PROVIDERS[0] ?? '');
            $model['basemodel'] = '';
            $model['mmproj'] = '';
            $model['modelinfo'] = [];

            if ($isPng) {
                $model['image'] = 'card_' . bin2hex(random_bytes(12)) . '.png';
                $portraitPath = DIR_ROOT . '/public/assets/img/models/' . $model['image'];
                $portrait = CharacterCardPng::portrait($bytes);
                if (file_put_contents($portraitPath, $portrait, LOCK_EX) !== strlen($portrait)) {
                    throw new \RuntimeException('Cannot save imported portrait.');
                }
            }

            $old = $_SESSION['character_card_import'] ?? null;
            if (is_array($old)) {
                $this->removeCharacterCardDraftPortrait($old);
            }

            $token = bin2hex(random_bytes(16));
            $_SESSION['character_card_import'] = [
                'token' => $token,
                'user_id' => User::id(),
                'expires' => time() + 3600,
                'model' => $model,
                'portrait' => $isPng ? $model['image'] : '',
            ];
            $this->characterCardImportResult(
                true, DIR_HOST . '/main/newmodel/' . $token
            );
        } catch (\Throwable $error) {
            if ($portraitPath !== null) {
                @unlink($portraitPath);
            }
            error_log('Mara character import: ' . $error->getMessage());
            $this->characterCardImportResult(false, LANG['CARD_ERROR']);
        }
    }

    private function characterCardDraft(array $vars): array
    {
        if (!isset($vars[0]) || $vars[0] === '') {
            return [];
        }
        $token = $vars[0];
        $draft = $_SESSION['character_card_import'] ?? null;
        if (!is_string($token) || !is_array($draft) ||
            !is_string($draft['token'] ?? null) ||
            !hash_equals($draft['token'], $token) ||
            ($draft['user_id'] ?? 0) !== User::id() ||
            ($draft['expires'] ?? 0) < time()) {
            $this->characterCardRedirect(LANG['CARD_DRAFT_EXPIRED']);
        }
        return array_merge($draft['model'], [
            'card_import_token' => $token,
            'card_csrf_token' => $this->characterCardCsrf(),
        ]);
    }

    private function saveCharacterCardModel(array $input): int|false
    {
        if (!array_key_exists('card_import_token', $input)) {
            return $this->MOD->save($input);
        }

        $token = $input['card_import_token'];
        $draft = $_SESSION['character_card_import'] ?? null;
        if (!is_string($token) || !is_array($draft) ||
            !is_string($draft['token'] ?? null) ||
            !hash_equals($draft['token'], $token) ||
            ($draft['user_id'] ?? 0) !== User::id() ||
            ($draft['expires'] ?? 0) < time() ||
            !$this->validCharacterCardCsrf($input['card_csrf_token'] ?? null)) {
            $_SESSION['message'] = LANG['CARD_DRAFT_EXPIRED'];
            return false;
        }

        if ((int)($input['id'] ?? 0) !== 0 ||
            !is_string($input['provider'] ?? null) ||
            !in_array($input['provider'], PROVIDERS, true) ||
            !is_string($input['basemodel'] ?? null) ||
            trim($input['basemodel']) === '' ||
            !is_string($input['name'] ?? null) ||
            trim($input['name']) === '' ||
            mb_strlen(trim($input['name']), 'UTF-8') > 64) {
            $_SESSION['message'] = LANG['CARD_SAVE_ERROR'];
            return false;
        }

        $connection = Database::getInstance()->getConnection();
        try {
            if (!$connection->begin_transaction()) {
                throw new \RuntimeException('Cannot begin character import transaction.');
            }
            $id = $this->MOD->save($input, $draft['model']['card_data']);
            if ($id === false || $id <= 0 || !$connection->commit()) {
                throw new \RuntimeException('Cannot save imported character.');
            }
        } catch (\Throwable $error) {
            $connection->rollback();
            error_log('Mara character save: ' . $error->getMessage());
            $_SESSION['message'] = LANG['CARD_SAVE_ERROR'];
            return false;
        }

        if (($input['image_path'] ?? '') !== ($draft['portrait'] ?? '')) {
            $this->removeCharacterCardDraftPortrait($draft);
        }
        unset($_SESSION['character_card_import']);
        return $id;
    }

    private function removeCharacterCardDraftPortrait(array $draft): void
    {
        $name = $draft['portrait'] ?? '';
        if (is_string($name) &&
            preg_match('/^card_[a-f0-9]{24}\.png$/D', $name)) {
            @unlink(DIR_ROOT . '/public/assets/img/models/' . $name);
        }
    }

    public function exportcard(array $vars = []): void
    {
        User::loggedIn();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            $this->characterCardRedirect(LANG['CARD_EXPORT_ERROR']);
        }

        try {
            $id = (int)($vars[0] ?? 0);
            $model = $id > 0 ? $this->MOD->getById($id) : null;
            if ($model === null ||
                (!User::isAdmin() && (int)$model['user_id'] !== 0 &&
                    (int)$model['user_id'] !== User::id())) {
                throw new \RuntimeException('Character is not accessible.');
            }

            $format = $_GET['format'] ?? 'png';
            if (!is_string($format) || !in_array($format, ['png', 'json'], true)) {
                throw new \InvalidArgumentException('Unsupported export format.');
            }
            $card = CharacterCardMapper::fromModel(
                $model,
                ($_GET['memory'] ?? '0') === '1'
            );

            if ($format === 'png') {
                $directory = realpath(DIR_ROOT . '/public/assets/img/models');
                $name = (string)($model['image'] ?? 'noimage.png');
                $file = DIR_ROOT . '/public/assets/img/models/' . $name;
                $realFile = realpath($file);
                if ($directory === false || $realFile === false ||
                    basename($name) !== $name || is_link($file) ||
                    dirname($realFile) !== $directory || !is_file($realFile) ||
                    filesize($realFile) > CharacterCardPng::MAX_BYTES) {
                    throw new \RuntimeException('Invalid character portrait.');
                }
                $png = file_get_contents($realFile);
                if ($png === false) {
                    throw new \RuntimeException('Cannot read character portrait.');
                }
                $body = CharacterCardPng::write($png, $card);
                $type = 'image/png';
            } else {
                $body = CharacterCard::encode($card);
                $type = 'application/json';
            }

            // Downloads must contain only the exported bytes.
            while (ob_get_level() > 0) {
                if (!ob_end_clean()) {
                    throw new \RuntimeException('Cannot clear download output buffer.');
                }
            }
            if (headers_sent()) {
                throw new \RuntimeException('Output was sent before character export.');
            }
            session_write_close();
            header('Content-Type: ' . $type);
            header('Content-Disposition: attachment; filename="character-' . $id . '.' . $format . '"');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: ' . strlen($body));
            echo $body;
            exit;
        } catch (\Throwable $error) {
            error_log('Mara character export: ' . $error->getMessage());
            $this->characterCardRedirect(LANG['CARD_EXPORT_ERROR']);
        }
    }
}
