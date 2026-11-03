<?php

declare(strict_types=1);

use Willy\Http\Application;

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

(new Application($root))->run();
