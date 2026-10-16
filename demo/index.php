<?php
// Direct visits must use the public entry, which initializes auth and sessions.
if (!isset($authorized)) {
    header('Location: ../public/');
    exit;
}
$fields = require __DIR__ . '/fields.php';
$resolvedImages = ['cover' => [], 'gallery' => []];
$formError = '';
$formResult = 'Select media above, then submit the form to see the PHP array.';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_media_csrf'] ?? '';
    if (!$authorized) {
        http_response_code(401);
        $formError = 'Sign in before submitting the form.';
    } elseif (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        $formError = 'Invalid CSRF token. Reload the page and try again.';
    } else {
        try {
            foreach ($fields as $key => $arguments) {
                $resolvedImages[$key] = media_browser($arguments, $_POST[$arguments['name']] ?? '[]');
            }
            $formResult = json_encode(['images' => $resolvedImages, 'message' => 'Normal POST form: PHP resolved the selected images in submitted order.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (InvalidArgumentException | OutOfBoundsException $error) {
            http_response_code(422);
            $formError = $error->getMessage();
        } catch (Throwable $error) {
            error_log((string) $error);
            http_response_code(500);
            $formError = 'Unable to resolve images. Check the server logs.';
        }
    }
}
$escapeHtml = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$guide = [
    'assets' => <<<'HTML'
<link rel="stylesheet" href="/vendor/media-browser/media-browser.css">
<link rel="stylesheet" href="/vendor/media-browser/media-field.css">
<script src="/vendor/media-browser/media-browser.js" defer></script>
<script src="/vendor/media-browser/media-field.js" defer></script>
HTML,
    'setup' => <<<'SHELL'
# From the package directory, after editing config.php:
php bin/setup.php
php bin/seed.php
SHELL,
    'serve' => <<<'SHELL'
php -d display_errors=0 -d display_startup_errors=0 -d upload_max_filesize=20M -d post_max_size=100M -S 127.0.0.1:8088 -t public
SHELL,
    'render' => <<<'PHP'
// Load Composer or the package's autoload.php first.
$options = [
    'name' => 'gallery_ids',
    'endpoint' => 'api.php',
    'profile' => 'product_gallery',
    'multiple' => true,
    'sortable' => true,
    'max_items' => 12,
];
$options['value'] = $savedImageIds ?? [];
echo media_browser($options); // Returns picker HTML, including its hidden input.
PHP,
    'backend' => <<<'PHP'
// Check your host's permission and CSRF token first.
$images = media_browser(
    $options,
    $_POST['gallery_ids'] ?? '[]'
);
$orderedIds = array_column($images, 'id');
// Save $orderedIds with your product.
PHP,
    'config' => <<<'PHP'
// Trusted server-side helper settings; never use browser input here.
$options['config'] = [
    'storage' => '/private/path/media-browser',
    'max_bytes' => 3 * 1024 * 1024,
    'page_size' => 8,
    'types' => ['image/jpeg' => 'jpg', 'image/png' => 'png'],
];
PHP,
    'laravel' => <<<'SHELL'
composer config repositories.media-browser path ./packages/media-browser
composer require "signum/media-browser:@dev"
php artisan vendor:publish --tag=media-browser-config
php artisan vendor:publish --tag=media-browser-assets
SHELL,
    'ci-route' => <<<'PHP'
// In app/Config/Routes.php, before catch-all routes:
require ROOTPATH . 'packages/media-browser/adapters/codeigniter/routes.php';
PHP,
    'mysql' => <<<'PHP'
// Configure this database section in config.php or your host's media config.
'database' => [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'your_application',
    'username' => 'media_user',
    'password' => getenv('MEDIA_BROWSER_DB_PASSWORD'),
    'table' => 'media_browser_files',
    'auto_create' => true,
],
PHP,
];
$guideCode = static fn (string $key): string => htmlspecialchars($guide[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Media Browser · PHP demo</title><link rel="stylesheet" href="assets/media-browser.css"><link rel="stylesheet" href="assets/media-field.css"><link rel="stylesheet" href="assets/demo-guide.css"><script src="assets/media-browser.js" defer></script><script src="assets/media-field.js" defer></script><script src="assets/demo.js" defer></script></head>
<body>
<main class="demo-shell">
  <header class="masthead"><a class="brand" href="./">◈ Media Browser</a><span>Standalone PHP · integration demo</span></header>
  <section id="login-panel" <?= $authorized ? 'hidden' : '' ?>><h1>Your media, in one place.</h1><p id="login-description">Sign in to open your library.</p><form id="login-form"><label>Password <input name="password" type="password" autocomplete="current-password" required></label><button>Sign in</button></form><p id="login-error" role="alert"></p></section>
  <noscript><p>The sample directory and guides work without JavaScript. Enable JavaScript to open the image picker.</p></noscript>
  <section id="demo-panel" <?= $authorized ? '' : 'hidden' ?>>
    <div class="hero"><span class="eyebrow">A SMALL LIBRARY. MANY POSSIBILITIES.</span><h1>Pick. Preview. Publish.</h1><p>Upload and organize your files, then select media for any PHP form. No framework or frontend dependencies.</p><button id="manage-library">Open media library</button><button class="secondary" id="logout">Sign out</button></div>
    <form id="example-form" class="examples" method="post" action="playground.php">
      <input type="hidden" name="_media_csrf" value="<?= $escapeHtml($_SESSION['csrf']) ?>">
      <section class="example-card"><span class="step">01 / SINGLE SELECTION</span><h2>Cover image</h2><p>Choose one image for an article or product.</p><?php if ($authorized) echo media_browser($fields['cover'] + ['value' => array_column($resolvedImages['cover'], 'id')]); ?></section>
      <section class="example-card"><span class="step">02 / SORTABLE GALLERY</span><h2>Product gallery</h2><p>Choose up to 12 images. Drag handles or use arrows to set their order.</p><?php if ($authorized) echo media_browser($fields['gallery'] + ['value' => array_column($resolvedImages['gallery'], 'id')]); ?></section>
      <section class="result-card"><div><h2>See the PHP image array</h2><p>Submit this normal POST form to validate images and return them in gallery order.</p></div><div><button type="submit">Resolve images in PHP</button> <button type="reset" class="secondary">Reset fields</button></div><p role="alert"><?= $escapeHtml($formError) ?></p><pre id="form-result" aria-live="polite"><?= $escapeHtml($formResult) ?></pre></section>
    </form>
  </section>
  <section class="demo-guide" id="integration-guide" aria-labelledby="guide-title">
    <span class="eyebrow">INTEGRATION &amp; USAGE</span>
    <h2 id="guide-title">Add this gallery to your project</h2>
    <p>Try the fields above: choose images, reorder them with the handles or arrows, then click <strong>Resolve images in PHP</strong>. The result is a validated PHP array in gallery order. This demo does not save a product.</p>
    <details open>
      <summary>1. Configure, create the table and seed rows</summary>
      <div class="guide-content">
        <ol>
          <li>Install PHP 8.1+, Fileinfo, sessions and PDO with SQLite or MySQL.</li>
          <li>Edit <code>config.php</code>: choose private file storage and the database driver/table. For MySQL, create a database and supply its credentials first.</li>
          <li>Run the setup and seed commands from the package root:</li>
        </ol>
        <pre><code><?= $guideCode('setup') ?></code></pre>
        <p>Setup creates the configured table. Seed inserts three missing sample-image rows into that same table and writes matching files to its storage directory. It also works when unrelated media already exists. First run: <code>3 added, 0 already present</code>; rerun: <code>0 added, 3 already present</code>. Existing sample IDs and unrelated records are preserved.</p>
        <p><code>php demo/seed.php</code> remains supported. GD is optional for illustrations. The samples must satisfy your allowed image types and size limit. These commands load the package's <code>config.php</code> and environment values, so align them with any host configuration.</p>
      </div>
    </details>
    <details>
      <summary>2. Run the demo and core PHP form</summary>
      <div class="guide-content">
        <pre><code><?= $guideCode('serve') ?></code></pre>
        <ol>
          <li>Open <code>http://127.0.0.1:8088/playground.php</code> and sign in if a password is configured.</li>
          <li>Open the picker; the seeded sample images are now available.</li>
          <li>Select and reorder images, then submit to see the PHP array.</li>
          <li>Try the <a href="form-example.php">separate core PHP form</a>, implemented in <code>demo/core-php.php</code> with the public entry <code>public/form-example.php</code>.</li>
        </ol>
        <p>The core PHP example loads the package without Composer, uses a normal POST form and hidden CSRF token, resolves submitted images and restores the selection. It leaves product saving to your application. A complete walkthrough is included in <code>docs/core-php.md</code>.</p>
      </div>
    </details>
    <details>
      <summary>3. Connect your PHP project</summary>
      <div class="guide-content">
        <h3>Core PHP</h3>
        <p>Requires PHP 8.1+, Fileinfo, sessions and PDO with SQLite or MySQL. Load the package's <code>autoload.php</code>. The standalone demo serves <code>public/</code> and uses <code>api.php</code>; an existing application can connect the kernel to its own protected endpoint. Set <code>MEDIA_BROWSER_PASSWORD</code> for non-local standalone access. Keep storage outside the document root.</p>
        <h3>Laravel</h3>
        <p>Place the package at <code>packages/media-browser</code>, then run these commands in your Laravel project:</p>
        <pre><code><?= $guideCode('laravel') ?></code></pre>
        <p>Define the <code>manage-media</code> Gate ability using your application's permissions. Set the field endpoint to <code>route('media-browser.api')</code>. Start with <code>demo/laravel.blade.php</code> and pass the host's configured storage options, as that example shows.</p>
        <h3>CodeIgniter 4</h3>
        <p>Register the <code>MediaBrowser</code> namespace through Composer or the host autoloader. Copy <code>adapters/codeigniter/MediaBrowser.php</code> into <code>app/Config/</code> and implement its <code>authorized()</code> check. Include the package route file:</p>
        <pre><code><?= $guideCode('ci-route') ?></code></pre>
        <p>Copy the four assets below into the host's public <code>vendor/media-browser/</code> directory. Use <code>site_url('media-browser/api')</code> as the endpoint and start with <code>demo/codeigniter.php</code>. Keep the route's CSRF filter enabled.</p>
      </div>
    </details>
    <details>
      <summary>4. Load the assets and render a field</summary>
      <div class="guide-content">
        <p>Include these once, using your host's published asset URLs:</p>
        <pre><code><?= $guideCode('assets') ?></code></pre>
        <p>Call the helper inside your authenticated form. Use the correct endpoint and storage settings for your host:</p>
        <pre><code><?= $guideCode('render') ?></code></pre>
        <p>With one argument, the helper returns picker HTML for your form. Selecting, removing or sorting images updates the hidden input with ordered IDs as JSON. Use <code>value</code> for saved IDs. Set <code>multiple</code> and <code>sortable</code> to <code>false</code> for a single cover image.</p>
      </div>
    </details>
    <details>
      <summary>5. Resolve submitted images on the backend</summary>
      <div class="guide-content">
        <p>Submit your form normally or with FormData. After verifying the user's permission and CSRF token, pass the same trusted options and the submitted field value. This two-argument call returns an array without output:</p>
        <pre><code><?= $guideCode('backend') ?></code></pre>
        <p>Laravel input: <code>$request-&gt;input('gallery_ids', '[]')</code>. CI4 input: <code>$this-&gt;request-&gt;getPost('gallery_ids') ?? '[]'</code>.</p>
        <p>Each returned record has <code>id</code>, <code>name</code>, <code>mime</code>, <code>extension</code>, <code>size</code>, <code>created_at</code> and <code>url</code>. PHP reloads metadata and rejects invalid, missing or disallowed images. Catch validation errors in your host's normal error handling.</p>
      </div>
    </details>
    <details>
      <summary>6. Change the defaults</summary>
      <div class="guide-content">
        <p>Change field arguments such as <code>max_items</code>, <code>button_text</code> or <code>sortable</code> directly. Use <code>config</code> to override the helper's <code>config.php</code> settings:</p>
        <pre><code><?= $guideCode('config') ?></code></pre>
        <p>These overrides apply to helper validation and field settings. To change live API upload limits, allowed types or page size, configure a matching server-side <code>profiles</code> entry and select it through <code>profile</code>. The included <code>product_gallery</code> profile allows images up to 5 MB and shows six files per page. Keep helper and API storage aligned.</p>
        <p>The working demo shares its field settings in <code>demo/fields.php</code> and resolves normal POST submissions in <code>demo/index.php</code>. <code>public/selection.php</code> remains available for AJAX forms. Full instructions are included in <code>README.md</code>, <code>docs/integration.md</code> and <code>docs/image-field.md</code>.</p>
      </div>
    </details>
    <details>
      <summary>7. Use MySQL instead of SQLite</summary>
      <div class="guide-content">
        <p>Enable <code>pdo_mysql</code>, create a database and configure the same connection and table for your API and form helper:</p>
        <pre><code><?= $guideCode('mysql') ?></code></pre>
        <p>For standalone setup, run <code>php bin/setup.php</code> with that configuration to create the metadata table. Use <code>php bin/setup.php --print</code> to inspect the SQL. Frameworks use their published media configuration or host migration process. Set <code>auto_create</code> to <code>false</code> once provisioned if runtime credentials have no CREATE permission.</p>
        <p>The existing form calls and image arrays stay the same. File bytes remain in private storage. SQLite is still the default; switching databases does not automatically migrate existing metadata. Read <code>docs/mysql.md</code> for environment variables, Laravel/CI4 examples and test instructions.</p>
      </div>
    </details>
  </section>
  <footer>Local demo · Files stay in your configured storage directory · PHP 8.1+</footer>
</main>
</body></html>
