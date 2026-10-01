<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once MARA_CONFIG . '/config.php';
require_once MARA_CONFIG . '/init.php';

use mara\core\Router;

Router::run();

?>