<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/autoload.php';
require dirname(__DIR__) . '/database_fixture.php';

use Illuminate\Foundation\Application;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use MediaBrowser\Bridge\Laravel\MediaBrowserServiceProvider;
use MediaBrowser\Bridge\Laravel\MediaController;
use MediaBrowser\MediaStore;

$root = sys_get_temp_dir() . '/media-laravel-check-' . bin2hex(random_bytes(6));
mkdir($root . '/bootstrap/cache', 0700, true);
mkdir($root . '/views', 0700, true);
function verify(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$app = new Application($root);
$metadataConfig = testMetadataConfig(['storage' => $root . '/media']);
Facade::setFacadeApplication($app);
$app->instance('config', new Repository([
    'app' => ['url' => 'http://localhost', 'key' => str_repeat('a', 32)],
    'view' => ['paths' => [], 'compiled' => $root . '/views'],
    'session' => ['lifetime' => 120, 'path' => '/', 'domain' => null, 'secure' => false, 'same_site' => 'lax'],
    'media-browser' => ['storage' => $root . '/media', 'prefix' => 'nested/media-browser', 'database' => $metadataConfig['database']],
]));
$app->instance('request', Request::create('http://localhost/'));
$app->register(Illuminate\Events\EventServiceProvider::class);
$app->register(Illuminate\Filesystem\FilesystemServiceProvider::class);
$app->register(Illuminate\Routing\RoutingServiceProvider::class);
$app->register(Illuminate\View\ViewServiceProvider::class);
$gate = new Illuminate\Auth\Access\Gate($app, static fn () => new Illuminate\Auth\GenericUser(['id' => 1]));
$app->instance(Illuminate\Contracts\Auth\Access\Gate::class, $gate);
$session = new Illuminate\Session\Store('framework-test', new Illuminate\Session\ArraySessionHandler(120));
$session->start();
$app->instance('session', $session);
$app->register(MediaBrowserServiceProvider::class);
try {
    $app->boot();
    $app['router']->getRoutes()->refreshNameLookups();
    $route = $app['router']->getRoutes()->getByName('media-browser.api');
    verify($route !== null && $route->uri() === 'nested/media-browser/api', 'Provider must register the configured route prefix.');
    verify(in_array('web', $route->gatherMiddleware(), true) && in_array('auth', $route->gatherMiddleware(), true), 'Routes must retain web/CSRF and auth middleware.');
    $store = $app->make(MediaStore::class);
    $request = Request::create('http://localhost/nested/media-browser/api?action=list');
    $request->setLaravelSession($session);
    $controller = new MediaController();
    try { $controller($request, $store); throw new RuntimeException('Undefined media ability was permitted.'); }
    catch (Illuminate\Auth\Access\AuthorizationException $expected) {}
    Gate::define('manage-media', static fn ($user): bool => $user->id === 1);
    $response = $controller($request, $store);
    verify($response->getStatusCode() === 200 && $response->getData(true)['total'] === 0, 'Controller must return native JSON.');
    $fixture = tempnam($root, 'fixture-');
    file_put_contents($fixture, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $item = $store->add($fixture, 'Cover.png', false);
    $response = $controller($request, $store);
    verify(str_contains($response->getData(true)['items'][0]['url'], '/nested/media-browser/api?action=file'), 'URLs must use the named host route.');
    $fileResponse = $controller(Request::create('http://localhost/nested/media-browser/api?action=file&id=' . $item['id']), $store);
    verify($fileResponse instanceof Symfony\Component\HttpFoundation\BinaryFileResponse && $fileResponse->headers->get('Content-Type') === 'image/png', 'Files must use a native binary response.');
    $rename = $controller(Request::create('http://localhost/nested/media-browser/api?action=rename', 'POST', ['id' => $item['id'], 'name' => 'Changed.png']), $store);
    verify($rename->getStatusCode() === 200 && $rename->getData(true)['item']['name'] === 'Changed.png', 'Host POST input must reach the kernel.');
    // Instantiate real CSRF middleware outside test mode; no bypass or fake token verifier.
    $app->detectEnvironment(static fn () => 'production');
    $app->instance('encrypter', new Illuminate\Encryption\Encrypter(str_repeat('a', 32), 'AES-256-CBC'));
    $csrf = new Illuminate\Foundation\Http\Middleware\VerifyCsrfToken($app, $app['encrypter']);
    $post = Request::create('http://localhost/nested/media-browser/api?action=rename', 'POST');
    $post->setLaravelSession($session);
    try { $csrf->handle($post, static fn () => new Illuminate\Http\JsonResponse(['ok' => true])); throw new RuntimeException('Missing CSRF accepted.'); }
    catch (Illuminate\Session\TokenMismatchException $expected) {}
    $post->headers->set('X-CSRF-TOKEN', $session->token());
    verify($csrf->handle($post, static fn () => new Illuminate\Http\JsonResponse(['ok' => true]))->getStatusCode() === 200, 'Real CSRF must accept the picker header.');
    echo 'PASS: Laravel ' . Application::VERSION . " provider, routes, middleware, Gate denial/allow, JSON, named URLs, binary response, rename and real CSRF middleware.\n";
    unset($fileResponse, $response, $rename, $controller, $store);
    $app->forgetInstance(MediaStore::class);
} finally {
    // Generated temporary test directory only; no application data is touched.
    $app->flush();
    cleanupTestMetadata($metadataConfig);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($root);
}
