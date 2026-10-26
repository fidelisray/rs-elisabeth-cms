<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): bool {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__.'/../app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (file_exists($file)) {
            require_once $file;

            return true;
        }
    }
    if (str_starts_with($class, 'Tests\\')) {
        $file = __DIR__.'/'.str_replace('\\', '/', substr($class, 6)).'.php';
        if (file_exists($file)) {
            require_once $file;

            return true;
        }
    }

    return false;
}, true, true);

$_ENV['APP_BASE_PATH'] = dirname(__DIR__);
$_SERVER['APP_BASE_PATH'] = dirname(__DIR__);

require __DIR__.'/../vendor/autoload.php';
