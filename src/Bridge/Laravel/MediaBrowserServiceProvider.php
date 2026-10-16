<?php
declare(strict_types=1);
namespace MediaBrowser\Bridge\Laravel;

use Illuminate\Support\ServiceProvider;
use MediaBrowser\MediaStore;

final class MediaBrowserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 3) . '/adapters/laravel/config.php', 'media-browser');
        $this->app->singleton(MediaStore::class, static fn ($app): MediaStore => new MediaStore($app['config']['media-browser']));
    }

    public function boot(): void
    {
        $root = dirname(__DIR__, 3);
        $this->publishes([$root . '/adapters/laravel/config.php' => config_path('media-browser.php')], 'media-browser-config');
        $this->publishes([
            $root . '/public/assets/media-browser.js' => public_path('vendor/media-browser/media-browser.js'),
            $root . '/public/assets/media-browser.css' => public_path('vendor/media-browser/media-browser.css'),
            $root . '/public/assets/host-example.js' => public_path('vendor/media-browser/host-example.js'),
            $root . '/public/assets/media-field.js' => public_path('vendor/media-browser/media-field.js'),
            $root . '/public/assets/media-field.css' => public_path('vendor/media-browser/media-field.css'),
        ], 'media-browser-assets');
        $this->loadRoutesFrom($root . '/adapters/laravel/routes.php');
    }
}
