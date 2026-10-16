<?php
declare(strict_types=1);

$config = require dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/autoload.php';
$sessionDirectory = $config['storage'] . '/sessions';
if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
    http_response_code(500);
    exit('Cannot initialize private session storage.');
}
session_name('standalone_media_browser');
if (!session_start(['save_path' => $sessionDirectory, 'use_strict_mode' => true, 'use_only_cookies' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'])) {
    http_response_code(500);
    exit('Cannot initialize session.');
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; media-src 'self'; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$authorized = $config['password'] === '' ? $local : ($_SESSION['authenticated'] ?? false);

function jsonResponse(array $data, int $status = 200): never
{
    (new \MediaBrowser\Http\Response($status, $data))->emit();
    exit;
}
