<?php
declare(strict_types=1);
$framework = __DIR__ . '/vendor/codeigniter4/framework/';
$temporary = sys_get_temp_dir() . '/media-ci-check-' . bin2hex(random_bytes(6)) . '/';
mkdir($temporary, 0700, true);
define('APPPATH', $framework . 'app/');
define('SYSTEMPATH', $framework . 'system/');
define('ROOTPATH', $framework);
define('WRITEPATH', $temporary);
define('FCPATH', $framework . 'public/');
define('ENVIRONMENT', 'testing');
define('CI_DEBUG', false);
// Load CI helpers first: Laravel and CI both define global helpers such as config().
require SYSTEMPATH . 'Common.php';
require __DIR__ . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/autoload.php';
require dirname(__DIR__) . '/database_fixture.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Config\\')) {
        $path = APPPATH . 'Config/' . substr($class, 7) . '.php';
        if (is_file($path)) require_once $path;
    }
});
require APPPATH . 'Config/Constants.php';
\Config\Services::autoloader()->initialize(new \Config\Autoload(), new \Config\Modules())->register();
require dirname(__DIR__, 2) . '/adapters/codeigniter/MediaBrowser.php';

function verify(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$settings = new class extends \Config\MediaBrowser {
    public bool $allowed = false;
    public array $metadataOptions = [];
    public function authorized(): bool { return $this->allowed; }
    public function options(): array { return array_replace(parent::options(), ['database' => $this->metadataOptions]); }
};
$metadataConfig = testMetadataConfig(['storage' => WRITEPATH . 'media-browser']);
$settings->metadataOptions = $metadataConfig['database'];
\CodeIgniter\Config\Factories::injectMock('config', 'MediaBrowser', $settings);
try {
    $appConfig = new \Config\App();
    $appConfig->baseURL = 'http://localhost/';
    $request = new \CodeIgniter\HTTP\IncomingRequest($appConfig, new \CodeIgniter\HTTP\URI('http://localhost/media-browser/api'), null, new \CodeIgniter\HTTP\UserAgent());
    $request->setMethod('GET');
    $request->setGlobal('get', ['action' => 'list']);
    $response = new \CodeIgniter\HTTP\Response($appConfig);
    $controller = new \MediaBrowser\Bridge\CodeIgniter\MediaController();
    $controller->initController($request, $response, new \Psr\Log\NullLogger());
    verify($controller->index()->getStatusCode() === 401, 'CI host authorization must fail closed.');
    $settings->allowed = true;
    $response = new \CodeIgniter\HTTP\Response($appConfig);
    $controller->initController($request, $response, new \Psr\Log\NullLogger());
    $result = $controller->index();
    verify($result->getStatusCode() === 200 && json_decode($result->getBody(), true)['total'] === 0, 'CI must return native JSON.');
    $store = new \MediaBrowser\MediaStore($settings->options());
    $source = tempnam($temporary, 'fixture-');
    file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $item = $store->add($source, 'cover.png', false);
    $request->setGlobal('get', ['action' => 'file', 'id' => $item['id']]);
    $result = $controller->index();
    verify($result instanceof \CodeIgniter\HTTP\DownloadResponse, 'CI files must use DownloadResponse.');
    verify(str_starts_with($result->getHeaderLine('Content-Disposition'), 'inline;'), 'CI file responses must preserve inline delivery.');
    verify($result->getHeaderLine('Content-Type') === 'image/png', 'CI downloads must preserve MIME.');
    $request->setMethod('POST');
    $request->setGlobal('get', ['action' => 'rename']);
    $request->setGlobal('post', ['id' => $item['id'], 'name' => 'Changed.png']);
    $controller->initController($request, new \CodeIgniter\HTTP\Response($appConfig), new \Psr\Log\NullLogger());
    $result = $controller->index();
    verify(json_decode($result->getBody(), true)['item']['name'] === 'Changed.png', 'CI POST input must reach the kernel.');
    verify($result->getHeaderLine(csrf_header()) !== '', 'CI responses must refresh the CSRF header.');
    // Exercise the actual framework security service with configured header tokens.
    $security = service('security');
    try { $security->verify($request); throw new RuntimeException('Missing CSRF accepted.'); }
    catch (\CodeIgniter\Security\Exceptions\SecurityException $expected) {}
    $request->setHeader(csrf_header(), csrf_hash());
    $security->verify($request);
    echo 'PASS: CodeIgniter ' . \CodeIgniter\CodeIgniter::CI_VERSION . " host authorization, native JSON, download, MIME, POST mapping, CSRF refresh and real CSRF validation.\n";
} finally {
    unset($store, $controller, $result, $response);
    cleanupTestMetadata($metadataConfig);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($temporary);
}
