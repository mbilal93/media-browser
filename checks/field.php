<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
require __DIR__ . '/database_fixture.php';
$config = testMetadataConfig(require dirname(__DIR__) . '/config.php');
$config['storage'] = sys_get_temp_dir() . '/media-field-check-' . bin2hex(random_bytes(6));
$source = tempnam(sys_get_temp_dir(), 'field-fixture-');
function verify(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function rejects(callable $callback, string $message): void {
    try { $callback(); } catch (InvalidArgumentException | OutOfBoundsException $expected) { return; }
    throw new RuntimeException($message);
}
try {
    file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $store = new \MediaBrowser\MediaStore($config);
    $a = $store->add($source, 'First.png', false);
    $b = $store->add($source, 'Second.png', false);
    $arguments = ['config' => $config, 'name' => 'gallery_ids', 'render' => false, 'endpoint' => '/nested/media?tenant=7', 'profile' => 'product_gallery'];
    $images = media_browser($arguments, json_encode([$b['id'], $a['id']]));
    verify(array_column($images, 'id') === [$b['id'], $a['id']], 'PHP must preserve the submitted drag order.');
    verify($images[0]['name'] === 'Second.png' && str_contains($images[0]['url'], 'tenant=7') && str_contains($images[0]['url'], 'profile=product_gallery'), 'Return real metadata and host/profile URLs.');
    verify(!isset($images[0]['path']) && !isset($images[0]['storage']), 'Return metadata without leaking private paths.');
    verify(media_browser($arguments, '[]') === [], 'Empty selections must resolve.');
    $formArguments = $arguments;
    unset($formArguments['render']);
    $formArguments['value'] = [$b['id'], $a['id']];
    ob_start();
    $fieldHtml = media_browser($formArguments);
    $unexpectedOutput = ob_get_clean();
    verify(is_string($fieldHtml) && $unexpectedOutput === '' && str_contains($fieldHtml, 'name="gallery_ids"'), 'One argument must return an embeddable picker field without echoing.');
    preg_match('/name="gallery_ids" value="([^"]*)"/', $fieldHtml, $hidden);
    verify(json_decode(htmlspecialchars_decode($hidden[1], ENT_QUOTES), true) === [$b['id'], $a['id']], 'The hidden input must contain initial IDs in order.');
    ob_start();
    $submittedImages = media_browser($formArguments, json_encode([$a['id'], $b['id']]));
    $unexpectedOutput = ob_get_clean();
    verify(is_array($submittedImages) && $unexpectedOutput === '' && array_column($submittedImages, 'id') === [$a['id'], $b['id']], 'Two arguments must resolve submitted images without rendering.');
    verify(media_browser($formArguments, null) === [], 'Explicit null submission must not reuse saved values.');
    verify(str_contains(media_browser(array_replace($formArguments, ['name' => 'product[images]'])), 'name="product[images]"'), 'Nested form names must render.');
    rejects(fn () => media_browser(array_replace($formArguments, ['name' => 'images[]'])), 'Ambiguous array suffix accepted for JSON field.');
    verify(\MediaBrowser\Configuration::resolve(['max_bytes' => 1], 'product_gallery')['max_bytes'] === 1, 'Explicit overrides must win over profiles.');
    verify(\MediaBrowser\Configuration::resolve(['field' => ['max_items' => 3]])['field']['name'] === 'images', 'Partial field defaults must preserve other settings.');
    rejects(fn () => media_browser($arguments, '[broken'), 'Malformed JSON accepted.');
    rejects(fn () => media_browser($arguments, [$a['id'], $a['id']]), 'Duplicate IDs accepted.');
    rejects(fn () => media_browser($arguments, [['id' => $a['id']]]), 'Client metadata accepted in place of IDs.');
    rejects(fn () => media_browser($arguments, [str_repeat('a', 32)]), 'Missing IDs accepted.');
    rejects(fn () => media_browser(array_replace($arguments, ['max_items' => 1]), [$a['id'], $b['id']]), 'Field limit ignored.');
    rejects(fn () => media_browser(array_replace($arguments, ['multiple' => false]), [$a['id'], $b['id']]), 'Single selection limit ignored.');
    rejects(fn () => media_browser(array_replace($arguments, ['profile' => '', 'config' => array_replace($config, ['max_bytes' => 1])]), [$a['id']]), 'Per-call config override ignored.');
    file_put_contents($source, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    $pdf = $store->add($source, 'document.pdf', false);
    rejects(fn () => media_browser($arguments, [$pdf['id']]), 'Non-images accepted.');
    ob_start();
    $rendered = media_browser(array_replace($arguments, ['render' => true, 'button_text' => '<Choose>']), [$b['id'], $a['id']]);
    $html = ob_get_clean();
    verify(str_contains($html, 'data-media-field=') && str_contains($html, 'name="gallery_ids"') && str_contains($html, '&lt;Choose&gt;'), 'Field markup must be reusable and escaped.');
    verify(array_column($rendered, 'id') === [$b['id'], $a['id']], 'Rendering must return the same validated ordered images.');
    $store->delete($a['id']);
    rejects(fn () => media_browser($arguments, [$a['id']]), 'Deleted image accepted.');
    echo "PASS: PHP function returns ordered real images, profiles/overrides, escaped field rendering, empty selections and rejection of invalid/duplicate/missing/non-image/oversized selections.\n";
} finally {
    unset($store);
    unlink($source);
    foreach (glob($config['storage'] . '/files/*') as $file) unlink($file);
    rmdir($config['storage'] . '/files');
    cleanupTestMetadata($config);
    rmdir($config['storage']);
}
