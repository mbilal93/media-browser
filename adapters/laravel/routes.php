<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use MediaBrowser\Bridge\Laravel\MediaController;

Route::middleware(config('media-browser.middleware', ['web', 'auth']))
    ->prefix(config('media-browser.prefix', 'media-browser'))
    ->group(static function (): void {
        Route::match(['get', 'post'], 'api', MediaController::class)->name('media-browser.api');
    });
