<?php
declare(strict_types=1);
// Only these bundled examples may be read. Never execute framework views here.
$samples = [
    'core-php' => ['Core PHP', 'demo/core-php.php', [
        'Configure config.php, then run php bin/setup.php and php bin/seed.php.',
        'Load autoload.php after your application bootstrap. Publish the four picker assets shown in the source.',
        'Render media_browser($options) inside your authenticated POST form with a hidden CSRF token.',
        'Validate permissions and CSRF before resolving submitted image IDs. Save ordered IDs with your product.',
    ], '$images = media_browser($options, $_POST[\'product_images\'] ?? \'[]\');'],
    'laravel' => ['Laravel', 'demo/laravel.blade.php', [
        'Place the package at packages/media-browser in your Laravel application.',
        'Run the Composer and Artisan commands below; the provider registers the media API route.',
        'Configure config/media-browser.php and define the manage-media Gate ability using your application permissions.',
        'Create and seed the media table using the same storage and database settings as the host configuration.',
        'Include the Blade sample inside an authenticated POST form using @csrf. Resolve submitted IDs in its controller after authorization and validation.',
    ], '$options = [\'name\' => \'gallery_ids\', \'endpoint\' => route(\'media-browser.api\'),\n    \'profile\' => \'product_gallery\',\n    \'config\' => \\MediaBrowser\\Configuration::profile(config(\'media-browser\'), \'product_gallery\')];\n$images = media_browser($options, $request->input(\'gallery_ids\', \'[]\'));'],
    'codeigniter' => ['CodeIgniter 4', 'demo/codeigniter.php', [
        'Install the package through Composer or register the MediaBrowser namespace with the host autoloader.',
        'Copy adapters/codeigniter/MediaBrowser.php into app/Config/. Configure database/storage and implement authorized().',
        'Include the bundled routes file from app/Config/Routes.php before catch-all routes. Keep its CSRF filter enabled.',
        'Copy media-browser.css, media-field.css, media-browser.js and media-field.js from public/assets/ into your host public/vendor/media-browser/.',
        'Provision and seed the same database/table. Include the view sample inside a protected POST form using csrf_field(), then resolve IDs in its controller.',
    ], '$options = [\'name\' => \'gallery_ids\', \'endpoint\' => site_url(\'media-browser/api\'),\n    \'profile\' => \'product_gallery\',\n    \'config\' => \\MediaBrowser\\Configuration::profile(config(\'MediaBrowser\')->options(), \'product_gallery\')];\n$images = media_browser($options, $this->request->getPost(\'gallery_ids\') ?? \'[]\');'],
];
$framework = $_GET['framework'] ?? 'core-php';
if (!is_string($framework) || !isset($samples[$framework])) {
    http_response_code(404);
    exit('Sample not found.');
}
[$title, $sourcePath, $steps, $backend] = $samples[$framework];
$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$source = file_get_contents(dirname(__DIR__) . '/' . $sourcePath);
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $escape($title) ?> sample · Media Browser</title><link rel="stylesheet" href="assets/media-browser.css"><link rel="stylesheet" href="assets/demo-guide.css"></head>
<body><main class="demo-shell">
<header class="masthead"><a class="brand" href="./">Media Browser</a><a href="./">All demos &amp; samples</a></header>
<section class="hero"><span class="eyebrow">INTEGRATION SAMPLE</span><h1><?= $escape($title) ?></h1><p><?= $framework === 'core-php' ? 'Run the standalone form here, then adapt its source to your application.' : 'Install this sample in your framework application. This page displays its source without requiring the framework.' ?></p><a class="demo-link" href="<?= $framework === 'core-php' ? 'form-example.php' : 'playground.php' ?>">Try <?= $framework === 'core-php' ? 'the core PHP form' : 'the shared picker demo' ?> →</a></section>
<nav class="sample-nav" aria-label="Integration samples"><?php foreach ($samples as $key => $sample): ?><a href="sample.php?framework=<?= $key ?>" <?= $framework === $key ? 'aria-current="page"' : '' ?>><?= $escape($sample[0]) ?></a><?php endforeach ?></nav>
<section class="demo-guide"><h2>1. Set up your application</h2><ol><?php foreach ($steps as $step): ?><li><?= $escape($step) ?></li><?php endforeach ?></ol>
<?php if ($framework === 'laravel'): ?><pre><code>composer config repositories.media-browser path ./packages/media-browser
composer require "signum/media-browser:@dev"
php artisan vendor:publish --tag=media-browser-config
php artisan vendor:publish --tag=media-browser-assets</code></pre><?php endif ?>
<?php if ($framework !== 'core-php'): ?><p>Setup and seed commands read the package's <code>config.php</code>, so align it with the host's media configuration before running <code>php bin/setup.php</code> and <code>php bin/seed.php</code>.</p><?php endif ?>
<?php if ($framework === 'codeigniter'): ?><p>From <code>app/Config/Routes.php</code>, adjust this path for your package location:</p><pre><code>require ROOTPATH . 'packages/media-browser/adapters/codeigniter/routes.php';</code></pre><?php endif ?>
<h2>2. Render the picker</h2><p>Source: <code><?= $escape($sourcePath) ?></code></p><pre><code><?= $escape($source === false ? 'Source file unavailable.' : $source) ?></code></pre>
<h2>3. Resolve submitted images</h2><p>Use the same trusted options for rendering and submission. After checking user permissions and form CSRF, this call returns image records in the selected order:</p><pre><code><?= $escape(str_replace('\n', "\n", $backend)) ?></code></pre><p>Save <code>array_column($images, 'id')</code> with your product. Selecting or sorting images updates the hidden input automatically.</p></section>
<footer><a href="./">Back to demo directory</a> · <a href="playground.php#integration-guide">Full integration guide</a></footer>
</main></body></html>
