<?php
declare(strict_types=1);

namespace mara\core\ajax;

use mara\core\User;
use mara\core\integration\ImageGenerator;

final class image_ajax
{
    public function generate(array $vars = []): void
    {
        User::loggedIn();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_REQUEST']]);
            return;
        }

        try {
            $data = json_decode(
                file_get_contents('php://input'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($data)) {
                throw new \InvalidArgumentException();
            }

            $token = $data['csrf_token'] ?? null;
            if (
                !is_string($token) ||
                !hash_equals(
                    (string)($_SESSION['image_csrf_token'] ?? ''),
                    $token
                ) ||
                empty($_SESSION['image_csrf_token'])
            ) {
                http_response_code(403);
                $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_CSRF']]);
                return;
            }

            $backend = $data['backend'] ?? '';
            $prompt = $data['prompt'] ?? '';
            if (
                !is_string($backend) ||
                !in_array($backend, ['forge', 'qwen2'], true) ||
                !is_string($prompt) ||
                trim($prompt) === '' ||
                strlen($prompt) > 20000
            ) {
                throw new \InvalidArgumentException();
            }

            $request = [];
            foreach ([
                'steps' => [1, 150],
                'width' => [256, 1536],
                'height' => [256, 1536],
                'seed' => [-1, 2147483647],
            ] as $key => [$min, $max]) {
                $value = $data[$key] ?? null;
                if (!is_int($value) || $value < $min || $value > $max) {
                    throw new \InvalidArgumentException();
                }
                $request[$key] = $value;
            }

            if ($request['width'] % 32 || $request['height'] % 32) {
                throw new \InvalidArgumentException();
            }

            $cfg = $data['cfg_scale'] ?? null;
            if (
                (!is_int($cfg) && !is_float($cfg)) ||
                !is_finite((float)$cfg) ||
                $cfg < 1 || $cfg > 30
            ) {
                throw new \InvalidArgumentException();
            }

            $sampler = $data['sampler'] ?? null;
            $negative = $data['negative_prompt'] ?? '';
            if (
                !is_string($sampler) || trim($sampler) === '' ||
                strlen($sampler) > 100 ||
                !is_string($negative) || strlen($negative) > 20000
            ) {
                throw new \InvalidArgumentException();
            }

            if ($request['seed'] === -1) {
                $request['seed'] = random_int(0, 2147483647);
            }

            $request['cfg_scale'] = $cfg;
            $request['sampler_name'] = $sampler;
            $request['negative_prompt'] = $negative;
            $count = $data['image_count'] ?? 1;
            if (
                !is_int($count) ||
                !in_array($count, [1, 2, 4, 6], true) ||
                ($backend === 'qwen2' && $count !== 1)
            ) {
                throw new \InvalidArgumentException();
            }

            $request['batch_size'] = 1;
            $request['n_iter'] = $count;

            if ($backend === 'forge') {
                $model = $data['model'] ?? '';
                $scheduler = $data['scheduler'] ?? '';
                if (
                    !is_string($model) || strlen($model) > 500 ||
                    !is_string($scheduler) || strlen($scheduler) > 100
                ) {
                    throw new \InvalidArgumentException();
                }
                if ($model !== '') {
                    $request['override_settings'] = [
                        'sd_model_checkpoint' => $model,
                    ];
                    $request['override_settings_restore_afterwards'] = true;
                }
                if ($scheduler !== '') {
                    $request['scheduler'] = $scheduler;
                }
            }

            $mode = $data['mode'] ?? 'txt2img';
            if (
                !is_string($mode) ||
                !in_array($mode, ['txt2img', 'img2img'], true)
            ) {
                throw new \InvalidArgumentException();
            }

            if ($mode === 'img2img') {
                $input = $data['init_image'] ?? null;
                if (!is_string($input) || $input === '') {
                    throw new \InvalidArgumentException();
                }

                $input = \mara\core\integration\ImageInput::normalize($input);

                if ($backend === 'qwen2') {
                    $size = $request['width'] . 'x' . $request['height'];
                    if (!in_array($size, ['992x992', '1152x864'], true)) {
                        throw new \InvalidArgumentException();
                    }

                    $info = getimagesizefromstring(base64_decode($input, true));
                    if (
                        $info === false ||
                        $info[0] !== $request['width'] ||
                        $info[1] !== $request['height']
                    ) {
                        throw new \InvalidArgumentException();
                    }

                    $request['extra_images'] = [$input];
                    $request['ref_image_args'] = 'resize_before_vae=false';
                    $mode = 'txt2img';
                } else {
                    $denoising = $data['denoising_strength'] ?? null;
                    if (
                        (!is_int($denoising) && !is_float($denoising)) ||
                        !is_finite((float)$denoising) ||
                        $denoising < 0 || $denoising > 1
                    ) {
                        throw new \InvalidArgumentException();
                    }

                    $request['init_images'] = [$input];
                    $request['denoising_strength'] = (float)$denoising;
                    $request['resize_mode'] = 0;
                    $request['include_init_images'] = false;
                }
            }

            $userId = (int)User::id();
            if ($userId <= 0) {
                throw new \RuntimeException('Missing authenticated user ID.');
            }

            $jobId = $data['job_id'] ?? bin2hex(random_bytes(16));
            if (
                !is_string($jobId) ||
                !preg_match('/^[a-f0-9]{32}$/D', $jobId)
            ) {
                throw new \InvalidArgumentException();
            }

            $_SESSION['image_job'] = [
                'job_id' => $jobId,
                'backend' => $backend,
            ];

            session_write_close();
            @set_time_limit(630);

            $started = microtime(true);
            $result = (new ImageGenerator())->generate(
                $backend,
                $prompt,
                $request,
                DIR_ROOT . '/public/genimages/user_' . $userId,
                '/genimages/user_' . $userId,
                600,
                $userId,
                $jobId,
                $mode
            );

            $this->reply([
                'success' => true,
                'image' => $result['image'],
                'images' => $result['images'],
                'cancelled' => $result['cancelled'] ?? false,
                'elapsed' => round(microtime(true) - $started, 2),
                'seed' => $request['seed'] >= 0 ? $request['seed'] : null,
            ]);
        } catch (\InvalidArgumentException | \JsonException $error) {
            http_response_code(400);
            $key = match ($error->getCode()) {
                413 => 'IMG_ERROR_IMAGE_LIMIT',
                422 => 'IMG_ERROR_IMAGE_INVALID',
                default => 'IMG_ERROR_PARAMS',
            };
            $this->reply(['success' => false, 'error' => LANG[$key]]);
        } catch (\Throwable $error) {
            if ($error->getCode() === 409) {
                http_response_code(409);
                $this->reply([
                    'success' => false,
                    'error' => LANG['IMG_ERROR_BACKEND_BUSY'],
                ]);
                return;
            }
            error_log('MaraImg generation: ' . $error->getMessage());
            http_response_code(500);
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_SERVER']]);
        }
    }

    public function upscale(array $vars = []): void
    {
        User::loggedIn();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_REQUEST']]);
            return;
        }

        try {
            $data = json_decode(
                file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR
            );
            if (!is_array($data)) {
                throw new \InvalidArgumentException();
            }

            $token = $data['csrf_token'] ?? null;
            if (
                !is_string($token) ||
                empty($_SESSION['image_csrf_token']) ||
                !hash_equals($_SESSION['image_csrf_token'], $token)
            ) {
                http_response_code(403);
                $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_CSRF']]);
                return;
            }

            $userId = (int)User::id();
            $input = $data['init_image'] ?? null;
            $scale = $data['upscale_factor'] ?? null;
            $upscaler = $data['upscaler'] ?? null;
            $jobId = $data['job_id'] ?? null;

            if (
                ($data['backend'] ?? '') !== 'forge' ||
                $userId <= 0 ||
                !is_string($input) || $input === '' ||
                !is_int($scale) || !in_array($scale, [2, 4], true) ||
                !is_string($upscaler) || $upscaler === '' ||
                strlen($upscaler) > 100 ||
                !is_string($jobId) ||
                !preg_match('/^[a-f0-9]{32}$/D', $jobId)
            ) {
                throw new \InvalidArgumentException();
            }

            $input = \mara\core\integration\ImageInput::normalize($input);
            $info = getimagesizefromstring(base64_decode($input, true));
            if (
                $info[0] * $scale > 8192 ||
                $info[1] * $scale > 8192 ||
                $info[0] * $info[1] * $scale * $scale > 32000000
            ) {
                throw new \InvalidArgumentException('Upscale dimensions too large.', 414);
            }

            $url = rtrim(trim((string)\mara\core\App::get('system.forge_url', '')), '/');
            if (
                filter_var($url, FILTER_VALIDATE_URL) === false ||
                !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            ) {
                throw new \RuntimeException('Invalid Forge URL.');
            }

            $_SESSION['image_job'] = [
                'job_id' => $jobId,
                'backend' => 'forge',
            ];
            session_write_close();
            @set_time_limit(630);

            if (
                $upscaler === 'None' ||
                !in_array($upscaler, $this->backendList($url, 'upscalers', 'name'), true)
            ) {
                throw new \InvalidArgumentException();
            }

            $started = microtime(true);
            $result = (new ImageGenerator())->generate(
                'forge',
                '',
                [
                    'image' => $input,
                    'resize_mode' => 0,
                    'upscaling_resize' => $scale,
                    'upscaler_1' => $upscaler,
                    'upscaler_2' => 'None',
                    'extras_upscaler_2_visibility' => 0,
                    'gfpgan_visibility' => 0,
                    'codeformer_visibility' => 0,
                    'show_extras_results' => true,
                ],
                DIR_ROOT . '/public/genimages/user_' . $userId,
                '/genimages/user_' . $userId,
                600,
                $userId,
                $jobId,
                'extra-single-image'
            );

            $this->reply([
                'success' => true,
                'image' => $result['image'],
                'images' => $result['images'],
                'elapsed' => round(microtime(true) - $started, 2),
            ]);
        } catch (\InvalidArgumentException | \JsonException $error) {
            http_response_code(400);
            $key = match ($error->getCode()) {
                413 => 'IMG_ERROR_IMAGE_LIMIT',
                414 => 'IMG_ERROR_UPSCALE_SIZE',
                422 => 'IMG_ERROR_IMAGE_INVALID',
                default => 'IMG_ERROR_PARAMS',
            };
            $this->reply(['success' => false, 'error' => LANG[$key]]);
        } catch (\Throwable $error) {
            error_log('MaraImg upscale: ' . $error->getMessage());
            $busy = $error->getCode() === 409;
            http_response_code($busy ? 409 : 500);
            $this->reply([
                'success' => false,
                'error' => LANG[$busy ? 'IMG_ERROR_BACKEND_BUSY' : 'IMG_ERROR_UPSCALE'],
            ]);
        }
    }

    public function deleteimage(array $vars = []): void
    {
        User::loggedIn();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_REQUEST']]);
            return;
        }

        try {
            $data = json_decode(
                file_get_contents('php://input'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if (!is_array($data)) {
                throw new \InvalidArgumentException();
            }

            $token = $data['csrf_token'] ?? null;
            if (
                !is_string($token) ||
                empty($_SESSION['image_csrf_token']) ||
                !hash_equals($_SESSION['image_csrf_token'], $token)
            ) {
                http_response_code(403);
                $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_CSRF']]);
                return;
            }

            $name = $data['name'] ?? null;
            $userId = (int)User::id();
            if (
                $userId <= 0 ||
                !is_string($name) ||
                !preg_match('/^gen_\d{8}_\d{6}_[a-f0-9]{16}\.(png|jpg|webp)$/D', $name)
            ) {
                throw new \InvalidArgumentException();
            }

            $directory = DIR_ROOT . '/public/genimages/user_' . $userId;
            $file = $directory . '/' . $name;
            $realDirectory = realpath($directory);
            $realFile = realpath($file);

            if (
                is_link($directory) ||
                is_link($file) ||
                $realDirectory === false ||
                $realFile === false ||
                dirname($realFile) !== $realDirectory ||
                !is_file($realFile)
            ) {
                http_response_code(404);
                $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_DELETE']]);
                return;
            }

            session_write_close();

            if (!unlink($realFile)) {
                throw new \RuntimeException('Cannot delete gallery image.');
            }

            $this->reply([
                'success' => true,
                'name' => $name,
                'message' => LANG['IMG_DELETED'],
            ]);
        } catch (\InvalidArgumentException | \JsonException $error) {
            http_response_code(400);
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_PARAMS']]);
        } catch (\Throwable $error) {
            error_log('MaraImg delete: ' . $error->getMessage());
            http_response_code(500);
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_DELETE']]);
        }
    }

    public function gallery(array $vars = []): void
    {
        User::loggedIn();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_REQUEST']]);
            return;
        }

        $userId = (int)User::id();
        if ($userId <= 0) {
            http_response_code(403);
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_REQUEST']]);
            return;
        }

        session_write_close();
        $directory = DIR_ROOT . '/public/genimages/user_' . $userId;
        $files = [];

        if (is_dir($directory)) {
            foreach (new \DirectoryIterator($directory) as $file) {
                if (
                    $file->isFile() &&
                    !$file->isLink() &&
                    preg_match(
                        '/^gen_\d{8}_\d{6}_[a-f0-9]{16}\.(png|jpg|webp)$/',
                        $file->getFilename()
                    )
                ) {
                    $files[] = [
                        'name' => $file->getFilename(),
                        'time' => $file->getMTime(),
                    ];
                }
            }
        }

        usort($files, static fn(array $a, array $b): int =>
            ($b['time'] <=> $a['time']) ?: strcmp($b['name'], $a['name'])
        );

        $images = [];
        foreach (array_slice($files, 0, 100) as $file) {
            $images[] = [
                'image' => rtrim(DIR_HOST, '/') . '/genimages/user_'
                    . $userId . '/' . rawurlencode($file['name']),
                'name' => $file['name'],
            ];
        }

        $this->reply(['success' => true, 'images' => $images]);
    }

    public function options(array $vars = []): void
    {
        User::loggedIn();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_REQUEST']]);
            return;
        }

        try {
            $backend = $_GET['backend'] ?? '';
            if (
                !is_string($backend) ||
                !in_array($backend, ['forge', 'qwen2'], true)
            ) {
                throw new \RuntimeException('Invalid backend.');
            }

            $url = rtrim(trim((string)\mara\core\App::get(
                'system.' . $backend . '_url',
                ''
            )), '/');

            if (
                filter_var($url, FILTER_VALIDATE_URL) === false ||
                !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            ) {
                throw new \RuntimeException('Invalid backend URL.');
            }

            session_write_close();
            @set_time_limit(45);

            $samplers = $this->backendList($url, 'samplers', 'name');
            $models = [];
            $schedulers = [];
            $upscalers = [];

            if ($backend === 'forge') {
                $models = $this->backendList($url, 'sd-models', 'title');
                $schedulers = $this->backendList($url, 'schedulers', 'name');
                $upscalers = array_values(array_filter(
                    $this->backendList($url, 'upscalers', 'name'),
                    static fn(string $name): bool => $name !== 'None'
                ));
            }

            if ($samplers === []) {
                throw new \RuntimeException('Backend returned no samplers.');
            }

            $this->reply([
                'success' => true,
                'backend' => $backend,
                'samplers' => $samplers,
                'models' => $models,
                'schedulers' => $schedulers,
                'upscalers' => $upscalers,
            ]);
        } catch (\Throwable $error) {
            error_log('MaraImg options: ' . $error->getMessage());
            http_response_code(502);
            $this->reply([
                'success' => false,
                'error' => LANG['IMG_ERROR_CAPABILITIES'],
            ]);
        }
    }

    private function backendList(
        string $url,
        string $endpoint,
        string $field
    ): array {
        $ch = curl_init($url . '/sdapi/v1/' . $endpoint);
        if ($ch === false) {
            throw new \RuntimeException('Cannot initialize backend request.');
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false || $status < 200 || $status >= 300) {
                throw new \RuntimeException('Backend options unavailable: ' . $endpoint);
            }
        } finally {
            curl_close($ch);
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !array_is_list($data)) {
            throw new \RuntimeException('Invalid backend options response.');
        }

        $options = [];
        foreach ($data as $item) {
            if (
                is_array($item) &&
                isset($item[$field]) &&
                is_string($item[$field]) &&
                $item[$field] !== ''
            ) {
                $options[] = $item[$field];
            }
        }

        return array_values(array_unique($options));
    }

    public function server(array $vars = []): void
    {
        User::loggedIn();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_REQUEST']]);
            return;
        }

        try {
            $data = json_decode(
                file_get_contents('php://input'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($data)) {
                throw new \InvalidArgumentException();
            }

            $token = $data['csrf_token'] ?? null;
            if (
                !is_string($token) ||
                empty($_SESSION['image_csrf_token']) ||
                !hash_equals($_SESSION['image_csrf_token'], $token)
            ) {
                http_response_code(403);
                $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_CSRF']]);
                return;
            }

            $backend = $data['backend'] ?? '';
            $action = $data['action'] ?? '';

            if (
                !is_string($backend) ||
                !in_array($backend, ['qwen2', 'forge'], true) ||
                !is_string($action) ||
                !in_array($action, ['test', 'start', 'stop'], true)
            ) {
                throw new \InvalidArgumentException();
            }

            if ($action !== 'test' && !User::isAdmin()) {
                http_response_code(403);
                $this->reply([
                    'success' => false,
                    'error' => LANG['IMG_SERVER_ADMIN_ONLY'],
                ]);
                return;
            }

            $url = rtrim(trim((string)\mara\core\App::get(
                'system.' . $backend . '_url',
                ''
            )), '/');

            session_write_close();
            @set_time_limit(45);

            if ($action === 'test') {
                $reachable = false;
                try {
                    if (
                        filter_var($url, FILTER_VALIDATE_URL) === false ||
                        !in_array(
                            parse_url($url, PHP_URL_SCHEME),
                            ['http', 'https'],
                            true
                        )
                    ) {
                        throw new \RuntimeException('Invalid backend URL.');
                    }
                    $reachable = $this->backendList($url, 'samplers', 'name') !== [];
                } catch (\Throwable $error) {
                    error_log('MaraImg connection test: ' . $error->getMessage());
                }

                $this->reply([
                    'success' => true,
                    'reachable' => $reachable,
                    'message' => LANG[$reachable
                        ? 'IMG_SERVER_READY'
                        : 'IMG_SERVER_UNAVAILABLE'],
                ]);
                return;
            }

            $service = $backend === 'qwen2'
                ? 'qwen-image'
                : 'sd-forge';

            $result = (new \mara\core\ServiceManager())->run(
                $action,
                $service
            );

            if (!$result['success']) {
                throw new \RuntimeException(
                    'Service command failed: '
                    . $result['exit_code'] . ' ' . $result['output']
                );
            }

            $this->reply([
                'success' => true,
                'message' => LANG[$action === 'start'
                    ? 'IMG_SERVER_START_SENT'
                    : 'IMG_SERVER_STOP_SENT'],
            ]);
        } catch (\InvalidArgumentException | \JsonException $error) {
            http_response_code(400);
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_PARAMS']]);
        } catch (\Throwable $error) {
            error_log('MaraImg server control: ' . $error->getMessage());
            http_response_code(500);
            $this->reply([
                'success' => false,
                'error' => LANG['IMG_SERVER_CONTROL_ERROR'],
            ]);
        }
    }

    public function progress(array $vars = []): void
    {
        $this->jobControl(false);
    }

    public function interrupt(array $vars = []): void
    {
        $this->jobControl(true);
    }

    private function jobControl(bool $interrupt): void
    {
        User::loggedIn();
        header('Content-Type: application/json; charset=utf-8');

        $method = $interrupt ? 'POST' : 'GET';
        if ($_SERVER['REQUEST_METHOD'] !== $method) {
            http_response_code(405);
            header('Allow: ' . $method);
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_REQUEST']]);
            return;
        }

        try {
            $data = $interrupt
                ? json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR)
                : $_GET;

            if (!is_array($data)) {
                throw new \InvalidArgumentException();
            }

            if ($interrupt) {
                $token = $data['csrf_token'] ?? null;
                if (
                    !is_string($token) ||
                    empty($_SESSION['image_csrf_token']) ||
                    !hash_equals($_SESSION['image_csrf_token'], $token)
                ) {
                    http_response_code(403);
                    $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_CSRF']]);
                    return;
                }
            }

            $jobId = $data['job_id'] ?? null;
            $sessionJob = $_SESSION['image_job'] ?? [];
            $userId = (int)User::id();
            if (
                !is_string($jobId) ||
                !preg_match('/^[a-f0-9]{32}$/D', $jobId) ||
                !is_array($sessionJob) ||
                !in_array($sessionJob['backend'] ?? '', ['forge', 'qwen2'], true) ||
                ($interrupt && ($sessionJob['backend'] ?? '') !== 'forge') ||
                !is_string($sessionJob['job_id'] ?? null) ||
                !hash_equals($sessionJob['job_id'], $jobId) ||
                $userId <= 0
            ) {
                throw new \InvalidArgumentException();
            }

            $url = rtrim(trim((string)\mara\core\App::get(
                'system.' . $sessionJob['backend'] . '_url',
                ''
            )), '/');
            if (
                filter_var($url, FILTER_VALIDATE_URL) === false ||
                !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            ) {
                throw new \RuntimeException('Invalid Forge URL.');
            }

            session_write_close();
            @set_time_limit(15);

            $result = \mara\core\integration\ImageJob::control(
                $url,
                $userId,
                $jobId,
                static function () use ($url, $interrupt): array {
                    $endpoint = $interrupt
                        ? '/sdapi/v1/interrupt'
                        : '/sdapi/v1/progress?skip_current_image=true';
                    $ch = curl_init($url . $endpoint);
                    if ($ch === false) {
                        throw new \RuntimeException('Cannot initialize job control.');
                    }

                    try {
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CONNECTTIMEOUT => 2,
                            CURLOPT_TIMEOUT => 5,
                            CURLOPT_HTTPHEADER => [
                                'Content-Type: application/json',
                                'Accept: application/json',
                            ],
                        ]);
                        if ($interrupt) {
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
                        }
                        $response = curl_exec($ch);
                        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
                            throw new \RuntimeException('Backend job control failed.');
                        }
                    } finally {
                        curl_close($ch);
                    }

                    if ($interrupt) {
                        return ['cancel_requested' => true];
                    }

                    $state = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                    $progress = $state['progress'] ?? null;
                    if (
                        (!is_int($progress) && !is_float($progress)) ||
                        !is_finite((float)$progress)
                    ) {
                        throw new \RuntimeException('Invalid progress response.');
                    }

                    return ['progress' => max(0.0, min(1.0, (float)$progress))];
                }
            );

            $this->reply(['success' => true] + $result);
        } catch (\InvalidArgumentException | \JsonException $error) {
            http_response_code(400);
            $this->reply(['success' => false, 'error' => LANG['IMG_ERROR_PARAMS']]);
        } catch (\Throwable $error) {
            error_log('MaraImg job control: ' . $error->getMessage());
            http_response_code(502);
            $this->reply([
                'success' => false,
                'error' => LANG[$interrupt ? 'IMG_ERROR_INTERRUPT' : 'IMG_ERROR_REQUEST'],
            ]);
        }
    }

    private function reply(array $data): void
    {
        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
