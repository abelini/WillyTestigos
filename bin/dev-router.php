<?php

declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'];
$path = '/';

if (is_string($uri)) {
    $parts = explode('?', $uri, 2);
    $path = $parts[0];
}

if ($path !== '/' && is_file(dirname(__DIR__) . '/public' . $path)) {
    return false;
}

require dirname(__DIR__) . '/public/index.php';
