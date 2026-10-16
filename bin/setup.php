<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/autoload.php';
$config = require dirname(__DIR__) . '/config.php';
$config['database']['auto_create'] = true;
if (in_array('--print', $argv, true)) {
    echo \MediaBrowser\Database::schema($config['database']['driver'], $config['database']['table']) . ";\n";
    exit;
}
try {
    new \MediaBrowser\MediaStore($config);
    echo "Media metadata table is ready. Existing rows and files were preserved.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Database setup failed. Check the configured connection, driver and table permissions.\n");
    exit(1);
}
