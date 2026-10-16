<?php
declare(strict_types=1);
require dirname(__DIR__) . '/adapters/core-php/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    jsonResponse(['error' => 'Method not allowed.'], 405);
}
if (!$authorized) jsonResponse(['error' => 'Sign in first.'], 401);
if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) jsonResponse(['error' => 'Invalid CSRF token.'], 403);
$fields = require dirname(__DIR__) . '/demo/fields.php';
try {
    $images = [];
    foreach ($fields as $key => $arguments) {
        $selection = $_POST[$arguments['name']] ?? '[]';
        if (!is_array($selection) && !is_string($selection)) throw new InvalidArgumentException('Invalid selection.');
        $images[$key] = media_browser($arguments, $selection);
    }
    jsonResponse(['images' => $images, 'message' => 'PHP resolved these images in submitted order. This demo does not save a product.']);
} catch (InvalidArgumentException | OutOfBoundsException $error) {
    jsonResponse(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log((string) $error);
    jsonResponse(['error' => 'Cannot resolve selected images.'], 500);
}
