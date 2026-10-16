<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
require __DIR__ . '/database_fixture.php';
use MediaBrowser\MediaStore;
$config = testMetadataConfig(require dirname(__DIR__) . '/config.php');
$config['storage'] = sys_get_temp_dir() . '/media-store-check-' . bin2hex(random_bytes(6));
$config['page_size'] = 2;
$source = tempnam(sys_get_temp_dir(), 'media-fixture-');
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
try {
    file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $store = new MediaStore($config);
    $first = $store->add($source, 'Cover 100%.png', false);
    $second = $store->add($source, 'Cover 100%.png', false);
    $third = $store->add($source, 'Other.png', false);
    check($first['id'] !== $second['id'], 'Same-name uploads must not overwrite each other.');
    check($store->listing()['total'] === 3, 'All uploads must be indexed.');
    check(count($store->listing('', 1)['items']) === 2, 'Page size must apply.');
    check(count($store->listing('', 2)['items']) === 1, 'Second page must contain the remaining file.');
    check($store->listing('%')['total'] === 2, 'LIKE wildcards must be treated as search text.');
    check($store->listing("' OR 1=1 --")['total'] === 0, 'Search must be parameterized.');
    check($store->listing('', 1, 'document')['total'] === 0, 'Type filtering must apply.');
    $renamed = $store->rename($first['id'], '<cover>.png');
    check($renamed['id'] === $first['id'], 'Renaming must preserve the media ID.');
    try { $store->rename($first['id'], '../escape.png'); throw new RuntimeException('Traversal name accepted.'); }
    catch (InvalidArgumentException $expected) {}
    file_put_contents($source, '<?php echo "not an image";');
    try { $store->add($source, 'attack.png', false); throw new RuntimeException('Disguised executable accepted.'); }
    catch (InvalidArgumentException $expected) {}
    $config['max_bytes'] = 1;
    $smallStore = new MediaStore($config);
    try { $smallStore->add($source, 'large.png', false); throw new RuntimeException('Size limit ignored.'); }
    catch (InvalidArgumentException $expected) {}
    unset($smallStore);
    $path = $store->path($store->get($first['id']));
    $store->delete($first['id']);
    check(!file_exists($path), 'Deletion must remove stored bytes.');
    try { $store->get($first['id']); throw new RuntimeException('Deleted record returned.'); }
    catch (OutOfBoundsException $expected) {}
    echo "PASS: upload, collisions, pagination, literal search, type filter, stable rename, invalid paths, MIME validation, size and deletion.\n";
} finally {
    unset($store);
    unlink($source);
    // Only files in this test's freshly generated temporary directory are removed.
    if (is_dir($config['storage'] . '/files')) {
        foreach (glob($config['storage'] . '/files/*') as $file) unlink($file);
        rmdir($config['storage'] . '/files');
    }
    cleanupTestMetadata($config);
    if (is_dir($config['storage'])) rmdir($config['storage']);
}
