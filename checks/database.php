<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
require __DIR__ . '/database_fixture.php';

use MediaBrowser\Database;
use MediaBrowser\MediaStore;

$config = testMetadataConfig(require dirname(__DIR__) . '/config.php');
$config['storage'] = sys_get_temp_dir() . '/media-db-check-' . bin2hex(random_bytes(6));
$config['database']['table'] = 'mb_check_' . bin2hex(random_bytes(6));
$source = tempnam(sys_get_temp_dir(), 'db-image-');
function verify(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
try {
    file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $store = new MediaStore($config);
    $driver = $config['database']['driver'];
    $pdo = Database::connect($config['database'], $config['storage']);
    if ($driver === 'mysql') verify($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === false || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === 0, 'MySQL must use native prepared statements.');
    $image = $store->add($source, 'Café 🫘 !_%.png', false);
    verify($store->listing('!_%')['total'] === 1, 'Literal LIKE metacharacters must work.');
    verify($store->get($image['id'])['name'] === 'Café 🫘 !_%.png', 'Unicode filenames must round-trip.');
    $manualConfig = $config;
    $manualConfig['database']['auto_create'] = false;
    $manualStore = new MediaStore($manualConfig);
    verify($manualStore->listing()['total'] === 1, 'Precreated tables must work with runtime DDL disabled.');
    $pdo->beginTransaction();
    $borrowed = new MediaStore($config, $pdo);
    verify($pdo->inTransaction(), 'Constructing with a host PDO must not commit its transaction.');
    $pdo->rollBack();
    unset($borrowed);
    try { new MediaStore(array_replace($config, ['database' => ['table' => 'media; DROP TABLE users']])); throw new RuntimeException('Unsafe identifier accepted.'); }
    catch (InvalidArgumentException $expected) {}
    try { Database::connect(['driver' => 'sqlite', 'dsn' => 'mysql:dbname=bad'], $config['storage']); throw new RuntimeException('Mismatched DSN accepted.'); }
    catch (InvalidArgumentException $expected) {}
    $table = Database::table($config['database']['table']);
    $trigger = Database::table($config['database']['table'] . '_deny');
    $makeTrigger = static function (string $event) use ($pdo, $driver, $table, $trigger): void {
        $sql = $driver === 'mysql'
            ? 'CREATE TRIGGER ' . $trigger . ' BEFORE ' . $event . ' ON ' . $table . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test failure'"
            : 'CREATE TRIGGER ' . $trigger . ' BEFORE ' . $event . ' ON ' . $table . " BEGIN SELECT RAISE(ABORT, 'Test failure'); END";
        $pdo->exec($sql);
    };
    $makeTrigger('DELETE');
    try { $store->delete($image['id']); throw new RuntimeException('Forced deletion failure ignored.'); }
    catch (PDOException $expected) {}
    verify(is_file($store->path($store->get($image['id']))), 'Rollback must restore the staged file.');
    $pdo->exec('DROP TRIGGER ' . $trigger);
    $makeTrigger('INSERT');
    $fileCount = count(glob($config['storage'] . '/files/*'));
    try { $store->add($source, 'failed.png', false); throw new RuntimeException('Forced insert failure ignored.'); }
    catch (PDOException $expected) {}
    verify(count(glob($config['storage'] . '/files/*')) === $fileCount, 'Failed insert must clean up uploaded bytes.');
    $pdo->exec('DROP TRIGGER ' . $trigger);
    $store->delete($image['id']);
    verify($store->listing()['total'] === 0, 'Record must be deleted after trigger removal.');
    echo 'PASS: ' . $driver . " custom tables, Unicode, literal search, native prepares, manual schema, host transaction safety and file rollback/cleanup.\n";
} finally {
    unset($store, $manualStore, $borrowed, $makeTrigger, $pdo);
    unlink($source);
    cleanupTestMetadata($config);
    foreach (glob($config['storage'] . '/files/*') as $file) unlink($file);
    rmdir($config['storage'] . '/files');
    rmdir($config['storage']);
}
