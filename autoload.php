<?php
declare(strict_types=1);
// Core PHP can use this loader instead of Composer. Loading it starts no session.
spl_autoload_register(static function (string $class): void {
    $prefix = 'MediaBrowser\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
    }
});
require_once __DIR__ . '/src/functions.php';
