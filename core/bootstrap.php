<?php

declare(strict_types=1);

define('MARA_ROOT', dirname(__DIR__));
define('MARA_CORE', MARA_ROOT . '/core');
define('MARA_CONFIG', MARA_ROOT . '/config');
define('MARA_TPL', MARA_ROOT . '/tpl');
define('MARA_PUBLIC', MARA_ROOT . '/public');

/*
 * Mara class autoloader
 *
 * mara\core\Example              -> /core/Example.php
 * mara\core\provider\Example     -> /core/provider/Example.php
 * mara\core\integration\Example  -> /core/integration/Example.php
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

spl_autoload_register(function (string $class): void {

    $prefix = 'mara\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));

    $file = MARA_ROOT . '/'
          . str_replace('\\', '/', $relativeClass)
          . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

?>