<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use MediaBrowser\MediaStore;
use MediaBrowser\Http\Kernel;
use MediaBrowser\Http\Upload;

$action = $_GET['action'] ?? 'list';
if (!is_string($action)) jsonResponse(['error' => 'Invalid action.'], 422);
$method = $_SERVER['REQUEST_METHOD'];
$writes = ['login', 'logout', 'upload', 'rename', 'delete'];
if (!in_array($action, [...$writes, 'session', 'list', 'file'], true)) jsonResponse(['error' => 'Unknown action.'], 404);
if (($method !== 'POST' && in_array($action, $writes, true)) || ($method !== 'GET' && !in_array($action, $writes, true))) {
    header('Allow: ' . (in_array($action, $writes, true) ? 'POST' : 'GET'));
    jsonResponse(['error' => 'Method not allowed.'], 405);
}
if (in_array($action, $writes, true) && !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    jsonResponse(['error' => 'Session expired or invalid CSRF token. Reload the page.'], 403);
}
if ($action === 'session') {
    try {
        $profile = $_GET['profile'] ?? '';
        if (!is_string($profile)) throw new InvalidArgumentException('Invalid profile.');
        $settings = \MediaBrowser\Configuration::profile($config, $profile);
        jsonResponse(['csrf' => $_SESSION['csrf'], 'csrfHeader' => 'X-CSRF-Token', 'authorized' => (bool) $authorized, 'passwordRequired' => $config['password'] !== '', 'localOnly' => $config['password'] === '',
            'settings' => ['maxBytes' => $settings['max_bytes'], 'pageSize' => $settings['page_size'], 'types' => array_keys($settings['types'])]]);
    } catch (InvalidArgumentException $error) { jsonResponse(['error' => $error->getMessage()], 422); }
}
if ($action === 'login') {
    $password = $_POST['password'] ?? '';
    if (!is_string($password) || $config['password'] === '' || !hash_equals($config['password'], $password)) jsonResponse(['error' => 'Invalid password.'], 401);
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    jsonResponse(['success' => true]);
}
if (!$authorized) jsonResponse(['error' => 'Sign in first. Without a configured password, access is limited to localhost.'], 401);
if ($action === 'logout') {
    $_SESSION = [];
    session_regenerate_id(true);
    jsonResponse(['success' => true]);
}
try {
    $kernel = new Kernel(new MediaStore($config), static fn (string $id): string => 'api.php?action=file&id=' . rawurlencode($id));
    $kernel->handle($method, $action, $_GET, $_POST, $action === 'upload' ? Upload::fromGlobals($_FILES) : [], true)->emit();
} catch (InvalidArgumentException $error) {
    jsonResponse(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log((string) $error);
    jsonResponse(['error' => 'Could not initialize media storage.'], 500);
}
