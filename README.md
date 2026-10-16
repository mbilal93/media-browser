# PHP Media Browser

Reusable image fields for core PHP, Laravel and CodeIgniter 4: upload, select, sort, submit hidden IDs and resolve them to PHP image arrays.

## 1. Configure storage and database

Install PHP 8.1+, Fileinfo, sessions and PDO with `pdo_sqlite` or `pdo_mysql`. GD is optional for illustrated seed images.

Open `config.php`. Set a writable private `storage` directory and the `database` driver/table. SQLite works by default. For MySQL, create a database first and supply its host, port, name, username and password. Use a dedicated table such as `media_browser_files`. [MySQL settings](docs/mysql.md).

The API, seed and form helper must use the same database, table and file storage. CLI commands read this package's `config.php` and its environment variables; they do not automatically load Laravel or CodeIgniter host configuration.

## 2. Create the table and seed sample rows

From the package directory, run:

```shell
php bin/setup.php
php bin/seed.php
```

Setup creates the table configured in `config.php`. Seeding inserts three missing sample-image records into that table and writes their matching files under `storage/files/`. It works with both SQLite and MySQL, including populated libraries. Reruns skip existing sample names and preserve their IDs and unrelated media.

Expected first run: `3 added, 0 already present`. Next run: `0 added, 3 already present`. The older `php demo/seed.php` command remains supported. These are media-library sample rows, not product or account records. No automatic SQLite-to-MySQL data migration occurs.

## 3. Run the demo and core PHP example

```shell
php -d display_errors=0 -d display_startup_errors=0 -d upload_max_filesize=20M -d post_max_size=100M -S 127.0.0.1:8088 -t public
```

1. Open **http://127.0.0.1:8088/** for the demo directory, with links to all three integrations.
2. Open **http://127.0.0.1:8088/playground.php** for the live picker. The seeded images appear in the picker.
3. Choose cover/gallery images and reorder the gallery with handles or arrows.
4. Submit the form to see the validated PHP image array in that order.
5. Open **http://127.0.0.1:8088/form-example.php** for the smaller core PHP form. Its source is [demo/core-php.php](demo/core-php.php).

Browse **/sample.php?framework=laravel**, **/sample.php?framework=codeigniter** or **/sample.php?framework=core-php** for setup and escaped source code. Framework samples run inside their host applications. The directory and source pages work without JavaScript or a database connection. Direct visits to `demo/index.php` redirect to the public directory.

The examples submit normal POST forms and restore the selected images. They do not save a product. Serve only `public/`; keep configuration/storage private. Set `MEDIA_BROWSER_PASSWORD` for non-local standalone access. The built-in server is for local development.

## 4. Integrate into an existing core PHP form

1. Put the package outside the document root and load its `autoload.php` after your application's bootstrap.
2. Connect a media API endpoint to your host's authentication, permissions and CSRF checks. Use the [core PHP endpoint outline](docs/integration.md#core-php). Keep its database/storage configuration aligned with the helper.
3. Publish these assets and include them once, using your application's URLs:

```html
<link rel="stylesheet" href="/vendor/media-browser/media-browser.css">
<link rel="stylesheet" href="/vendor/media-browser/media-field.css">
<script src="/vendor/media-browser/media-browser.js" defer></script>
<script src="/vendor/media-browser/media-field.js" defer></script>
```

4. Inside your normal POST form, render a field:

```php
$args = [
    'name' => 'product_images',
    'endpoint' => '/admin/media.php', // Your protected API URL.
    'profile' => 'product_gallery',
    'multiple' => true,
    'sortable' => true,
    'max_items' => 12,
    'value' => $savedImageIds ?? [],
];
echo media_browser($args);
```

5. After validating the host user and form CSRF token, read the submitted field:

```php
$images = media_browser($args, $_POST['product_images'] ?? '[]');
$orderedIds = array_column($images, 'id');
// Save $orderedIds with your product.
```

One argument returns picker HTML. Two arguments return validated image records without output. Selection/sorting/removal update the hidden input automatically. Each image has `id`, `name`, `mime`, `extension`, `size`, `created_at` and `url`. [Field options and nested names](docs/image-field.md).

## 5. Integrate with a framework

**Laravel**

1. Put the package at `packages/media-browser` in the host project.
2. Install and publish:

```shell
composer config repositories.media-browser path ./packages/media-browser
composer require "signum/media-browser:@dev"
php artisan vendor:publish --tag=media-browser-config
php artisan vendor:publish --tag=media-browser-assets
```

3. Configure the host's media database/storage and define the `manage-media` Gate ability.
4. Provision and seed using the same settings. Use [demo/laravel.blade.php](demo/laravel.blade.php) and `route('media-browser.api')` as the endpoint. [Full Laravel setup](docs/integration.md#laravel).

**CodeIgniter 4**

1. Register the package namespace or install its Composer package.
2. Copy `adapters/codeigniter/MediaBrowser.php` into `app/Config/`, configure storage/database, and implement `authorized()`.
3. Include `adapters/codeigniter/routes.php` before catch-all routes; keep CSRF enabled.
4. Provision/seed the same table and publish the four assets above. Use [demo/codeigniter.php](demo/codeigniter.php) and `site_url('media-browser/api')`. [Full CI4 setup](docs/integration.md#codeigniter-4).

## Configuration and checks

Pass trusted `config => [...]` settings to override helper defaults. Use named server profiles for API upload types, size limits and pagination. Never take configuration from browser input. The included `product_gallery` profile shows six images per page and allows files up to 5 MB.

Run `composer test`. For framework checks: `composer install --working-dir=checks/frameworks`, then `composer test:frameworks`. With a local MySQL binary, run `composer test:mysql` against a disposable server.

[Core PHP walkthrough](docs/core-php.md) · [Integration](docs/integration.md) · [MySQL](docs/mysql.md) · [Field options](docs/image-field.md) · [Test results](docs/testing.md)
