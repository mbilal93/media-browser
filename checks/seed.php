<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
require __DIR__ . '/database_fixture.php';
$config = testMetadataConfig(require dirname(__DIR__) . '/config.php');
$config['storage'] = sys_get_temp_dir() . '/media-seed-check-' . bin2hex(random_bytes(6));
// A non-default table is required to catch hardcoded "media" inserts.
if ($config['database']['driver'] === 'sqlite') $config['database']['table'] = 'custom_seed_images';
$temporary = tempnam(sys_get_temp_dir(), 'existing-image-');
function verify(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
try {
    $store = new \MediaBrowser\MediaStore($config);
    file_put_contents($temporary, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $existing = $store->add($temporary, 'Existing user image.png', false);
    $config['database']['auto_create'] = false;
    $seeder = new \MediaBrowser\DemoSeeder($config);
    $first = $seeder->run();
    verify($first['created'] === 3 && $first['skipped'] === 0, 'Populated libraries must receive the three missing sample rows.');
    verify($store->listing()['total'] === 4 && $store->get($existing['id'])['name'] === 'Existing user image.png', 'Unrelated records must be preserved.');
    foreach ($first['images'] as $image) {
        verify(is_file($store->path($image)), 'Every seeded record must have matching bytes.');
        verify($store->get($image['id'])['name'] === $image['name'], 'Rows must exist in the configured table.');
    }
    $second = $seeder->run();
    verify($second['created'] === 0 && $second['skipped'] === 3, 'Repeated seed runs must not duplicate images.');
    verify(array_column($first['images'], 'id') === array_column($second['images'], 'id'), 'Repeated runs must preserve IDs.');
    $store->delete($first['images'][1]['id']);
    $third = $seeder->run();
    verify($third['created'] === 1 && $third['skipped'] === 2, 'Partial seed data must receive only the missing row.');
    $field = media_browser(['config' => $config], array_column($third['images'], 'id'));
    verify(count($field) === 3, 'Seed rows must resolve through the backend image helper.');
    $badConfig = $config;
    $badConfig['types'] = ['application/pdf' => 'pdf'];
    try { (new \MediaBrowser\DemoSeeder($badConfig))->run(); throw new RuntimeException('Unsupported seed types accepted.'); }
    catch (InvalidArgumentException $expected) {}
    echo 'PASS: ' . $config['database']['driver'] . " seeding into configured custom tables, matching files, preserving user rows/IDs, reruns, partial seed completion and form compatibility.\n";
} finally {
    unset($store, $seeder);
    unlink($temporary);
    cleanupTestMetadata($config);
    foreach (glob($config['storage'] . '/files/*') as $file) unlink($file);
    rmdir($config['storage'] . '/files');
    rmdir($config['storage']);
}
