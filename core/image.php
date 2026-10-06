<?php
declare(strict_types=1);

namespace mara\core;

final class image
{
    use Render;

    public function view(array $vars = []): void
    {
        User::loggedIn();

        $backend = (string) App::get('system.image_backend', 'qwen2');

        if (!in_array($backend, ['forge', 'qwen2'], true)) {
            $backend = 'qwen2';
        }

        if (empty($_SESSION['image_csrf_token'])) {
            $_SESSION['image_csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->show([
            'header' => DIR_TPL . '/imgheader.tpl.php',
            'page' => DIR_TPL . '/imgmain.tpl.php',
            'pagedata' => [
                'lang' => strtolower(
                    (string) App::get('system.language', 'HU')
                ),
                'backend' => $backend,
                'csrfToken' => $_SESSION['image_csrf_token'],
            ],
        ]);
    }
}
