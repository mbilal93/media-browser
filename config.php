<?php
declare(strict_types=1);

return [
    'storage' => getenv('MEDIA_BROWSER_STORAGE') ?: __DIR__ . '/storage',
    'password' => getenv('MEDIA_BROWSER_PASSWORD') ?: '',
    'database' => [
        'driver' => getenv('MEDIA_BROWSER_DB_DRIVER') ?: 'sqlite',
        'dsn' => getenv('MEDIA_BROWSER_DB_DSN') ?: '',
        'host' => getenv('MEDIA_BROWSER_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('MEDIA_BROWSER_DB_PORT') ?: 3306),
        'name' => getenv('MEDIA_BROWSER_DB_NAME') ?: '',
        'username' => getenv('MEDIA_BROWSER_DB_USER') ?: '',
        'password' => getenv('MEDIA_BROWSER_DB_PASSWORD') === false ? '' : getenv('MEDIA_BROWSER_DB_PASSWORD'),
        'table' => getenv('MEDIA_BROWSER_DB_TABLE') ?: 'media_browser_files',
        'auto_create' => filter_var(getenv('MEDIA_BROWSER_DB_AUTO_CREATE') === false ? 'true' : getenv('MEDIA_BROWSER_DB_AUTO_CREATE'), FILTER_VALIDATE_BOOLEAN),
        'options' => [], // Optional PDO connection options, including TLS options for MySQL.
    ],
    'max_bytes' => 20 * 1024 * 1024,
    'page_size' => 12,
    'field' => ['name' => 'images', 'multiple' => true, 'sortable' => true, 'max_items' => 24, 'endpoint' => 'api.php', 'button_text' => 'Choose images'],
    // Trusted server-side profiles apply to both the field and API, never client config.
    'profiles' => [
        'product_gallery' => [
            'page_size' => 6,
            'max_bytes' => 5 * 1024 * 1024,
            'types' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
        ],
    ],
    'types' => [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
        'image/webp' => 'webp', 'application/pdf' => 'pdf',
        'video/mp4' => 'mp4', 'video/webm' => 'webm',
    ],
];
