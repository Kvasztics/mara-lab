<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Budapest');

define('DB_HOST', 'localhost');
define('DB_USER', 'maralab');
define('DB_PASS', '');
define('DB_NAME', 'maralab');
define('DB_CHARSET', 'utf8mb4');
define('PROVIDERS', ['llamacpp', 'ollama']);

?>