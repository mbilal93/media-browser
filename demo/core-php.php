<?php
declare(strict_types=1);
// public/form-example.php has loaded the standalone auth/CSRF bootstrap.
// In an existing core PHP app, load its own bootstrap and package autoload.php instead.
$options = ['name' => 'product_images', 'endpoint' => 'api.php', 'profile' => 'product_gallery',
    'multiple' => true, 'sortable' => true, 'max_items' => 12];
$images = [];
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_media_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        $errorMessage = 'Invalid CSRF token.';
    } else {
        try {
            $images = media_browser($options, $_POST['product_images'] ?? '[]');
            // Save array_column($images, 'id') with your product here.
        } catch (InvalidArgumentException | OutOfBoundsException $error) {
            http_response_code(422);
            $errorMessage = $error->getMessage();
        } catch (Throwable $error) {
            error_log((string) $error);
            http_response_code(500);
            $errorMessage = 'Cannot resolve selected images.';
        }
    }
}
$options['value'] = array_column($images, 'id');
$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Core PHP form example</title>
    <link rel="stylesheet" href="assets/media-browser.css">
    <link rel="stylesheet" href="assets/media-field.css">
    <link rel="stylesheet" href="assets/demo-guide.css">
    <script src="assets/media-browser.js" defer></script>
    <script src="assets/media-field.js" defer></script>
</head>
<body><main class="demo-shell">
    <header class="masthead"><a class="brand" href="./">Media Browser</a><span>Core PHP · normal POST form</span></header>
    <section class="hero"><h1>Product images</h1><p>Choose images, reorder them and submit. PHP validates the hidden input and returns the ordered image array. This example does not save a product.</p></section>
    <form method="post" action="form-example.php" class="example-card">
        <input type="hidden" name="_media_csrf" value="<?= $escape($_SESSION['csrf']) ?>">
        <?= media_browser($options) ?>
        <p role="alert"><?= $escape($errorMessage) ?></p>
        <button type="submit">Submit product images</button> <button type="reset" class="secondary">Reset</button>
    </form>
    <section class="demo-guide"><h2>Backend image array</h2><pre id="core-result"><?= $escape(json_encode($images, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) ?></pre><p>Implementation: <code>demo/core-php.php</code>. Public entry point: <code>public/form-example.php</code>.</p></section>
</main></body></html>
