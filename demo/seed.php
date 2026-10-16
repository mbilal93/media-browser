<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/autoload.php';
$config = require dirname(__DIR__) . '/config.php';
try {
    $result = (new \MediaBrowser\DemoSeeder($config))->run();
    $driver = $config['database']['driver'] ?? 'sqlite';
    $table = $config['database']['table'] ?? 'media';
    echo "Seed complete: {$result['created']} added, {$result['skipped']} already present in {$driver} table {$table}.\n";
    echo "Each inserted row has a matching image in the configured storage/files directory. Existing unrelated media was preserved.\n";
} catch (PDOException $error) {
    fwrite(STDERR, "Seed failed. Check config.php database credentials/table and run php bin/setup.php before seeding.\n");
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, 'Seed failed: ' . $error->getMessage() . "\n");
    exit(1);
}
