<?php
declare(strict_types=1);
return array_replace(\MediaBrowser\Configuration::defaults(storage_path('app/private/media-browser')), [
    'storage' => storage_path('app/private/media-browser'),
    'prefix' => 'media-browser',
    'middleware' => ['web', 'auth'],
    'ability' => 'manage-media',
]);
