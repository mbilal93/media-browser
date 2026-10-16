<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
require __DIR__ . '/database_fixture.php';

use MediaBrowser\MediaStore;
use MediaBrowser\Http\Kernel;
use MediaBrowser\Http\Upload;

$config = testMetadataConfig(require dirname(__DIR__) . '/config.php');
$config['storage'] = sys_get_temp_dir() . '/media-kernel-check-' . bin2hex(random_bytes(6));
$source = tempnam(sys_get_temp_dir(), 'kernel-fixture-');
function expect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
try {
    $initialSession = session_status();
    file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $store = new MediaStore($config);
    $item = $store->add($source, 'cover.png', false);
    $kernel = new Kernel($store, static fn (string $id): string => '/nested/admin/media?action=file&id=' . $id);
    ob_start();
    $denied = $kernel->handle('GET', 'list');
    $output = ob_get_clean();
    expect($denied->status === 401 && $output === '', 'Kernel must deny by default and never emit.');
    expect(session_status() === $initialSession, 'Core must not start or change a host session.');
    expect($kernel->handle('GET', 'delete', [], ['id' => $item['id']], [], true)->status === 405, 'Reads must not mutate.');
    expect($kernel->handle('POST', 'unknown', [], [], [], true)->status === 404, 'Unknown actions must not fall through to deletion.');
    $list = $kernel->handle('get', 'list', [], [], [], true);
    expect($list->data['items'][0]['url'] === '/nested/admin/media?action=file&id=' . $item['id'], 'Host URL generation must be preserved.');
    expect(!isset($store->get($item['id'])['url']), 'Persistence must not contain route assumptions.');
    expect($kernel->handle('GET', 'list', ['q' => ['invalid']], [], [], true)->status === 422, 'Nested query inputs must be rejected.');
    expect($kernel->handle('POST', 'rename', [], ['id' => $item['id'], 'name' => ['invalid']], [], true)->status === 422, 'Malformed mutation inputs must not cause a 500.');
    $session = $kernel->handle('GET', 'session', [], [], [], true, ['csrf' => 'ci-token', 'csrfHeader' => 'X-CUSTOM-CSRF', 'csrfField' => 'csrf_test']);
    expect($session->data['csrfField'] === 'csrf_test', 'Host CSRF metadata must pass through.');
    $download = $kernel->handle('GET', 'file', ['id' => $item['id']], [], [], true);
    expect($download->file === $store->path($store->get($item['id'])) && $download->headers['Content-Type'] === 'image/png', 'File responses must carry a private path and MIME.');
    $rename = $kernel->handle('POST', 'rename', [], ['id' => $item['id'], 'name' => 'changed.png'], [], true);
    expect($rename->data['item']['url'] === $list->data['items'][0]['url'], 'Renames must preserve host URLs.');
    expect($kernel->handle('POST', 'upload', [], [], [new Upload('', 'failed.png', UPLOAD_ERR_INI_SIZE)], true)->status === 422, 'Upload transport errors must be reported.');
    try { Upload::fromGlobals(['files' => ['name' => ['nested' => ['bad']], 'tmp_name' => [], 'error' => []]]); throw new RuntimeException('Malformed uploads accepted.'); }
    catch (InvalidArgumentException $expected) {}
    $kernel->handle('POST', 'delete', [], ['id' => $item['id']], [], true);
    expect($kernel->handle('GET', 'file', ['id' => $item['id']], [], [], true)->status === 404, 'Deleted files must return 404.');
    echo "PASS: stateless kernel, default denial, method safety, host URLs, CSRF metadata, malformed inputs, uploads, file descriptors, rename and delete.\n";
} finally {
    unset($kernel, $store);
    unlink($source);
    foreach (glob($config['storage'] . '/files/*') as $file) unlink($file);
    rmdir($config['storage'] . '/files');
    cleanupTestMetadata($config);
    rmdir($config['storage']);
}
