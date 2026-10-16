<?php
declare(strict_types=1);
namespace MediaBrowser\Http;

use MediaBrowser\MediaStore;

/** Stateless operation dispatch. The host must verify authentication and CSRF first. */
final class Kernel
{
    private \Closure $url;
    public function __construct(private MediaStore $store, callable $fileUrl)
    {
        $this->url = \Closure::fromCallable($fileUrl);
    }

    public function handle(string $method, string $action, array $query = [], array $input = [], array $uploads = [], bool $authorized = false, array $session = []): Response
    {
        $headers = ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'];
        $verbs = ['session' => 'GET', 'list' => 'GET', 'file' => 'GET', 'upload' => 'POST', 'rename' => 'POST', 'delete' => 'POST'];
        if (!isset($verbs[$action])) return new Response(404, ['error' => 'Unknown action.'], $headers);
        if (strtoupper($method) !== $verbs[$action]) return new Response(405, ['error' => 'Method not allowed.'], $headers + ['Allow' => $verbs[$action]]);
        if (!$authorized) return new Response(401, ['error' => 'Media management permission is required.'], $headers);
        try {
            $text = static function (array $values, string $key, string $default = ''): string {
                $value = $values[$key] ?? $default;
                if (!is_string($value) && !is_int($value)) throw new \InvalidArgumentException('Invalid ' . $key . '.');
                return (string) $value;
            };
            $profile = $text($query, 'profile');
            $store = $this->store->profile($profile);
            $decorate = fn (array $row): array => $row + ['url' => $profile === '' ? ($this->url)($row['id']) : \MediaBrowser\Url::query(($this->url)($row['id']), ['profile' => $profile])];
            if ($action === 'session') return new Response(200, $session + ['authorized' => true, 'settings' => $store->limits()], $headers);
            if ($action === 'list') {
                $data = $store->listing(substr($text($query, 'q'), 0, 240), (int) $text($query, 'page', '1'), $text($query, 'type'));
                $data['items'] = array_map($decorate, $data['items']);
                return new Response(200, $data, $headers);
            }
            if ($action === 'file') {
                $row = $store->get($text($query, 'id'));
                $path = $store->path($row);
                if (!is_file($path)) throw new \OutOfBoundsException('File not found.');
                return new Response(200, [], $headers + [
                    'Content-Type' => $row['mime'], 'Content-Length' => (string) filesize($path),
                    'Content-Disposition' => 'inline; filename="media.' . $row['extension'] . '"; filename*=UTF-8\'\'' . rawurlencode($row['name']),
                ], $path);
            }
            if ($action === 'upload') {
                if (!$uploads) throw new \InvalidArgumentException('Choose files to upload. Check PHP POST limits.');
                $items = $errors = [];
                foreach ($uploads as $upload) {
                    if (!$upload instanceof Upload) throw new \InvalidArgumentException('Invalid upload descriptor.');
                    try {
                        if ($upload->error !== UPLOAD_ERR_OK) throw new \InvalidArgumentException('Upload failed (PHP code ' . $upload->error . ').');
                        $items[] = $decorate($store->add($upload->path, $upload->name));
                    } catch (\InvalidArgumentException $error) {
                        $errors[] = ['name' => $upload->name, 'error' => $error->getMessage()];
                    }
                }
                return new Response($items ? 201 : 422, ['items' => $items, 'errors' => $errors], $headers);
            }
            $id = $text($input, 'id');
            if ($action === 'rename') return new Response(200, ['item' => $decorate($store->rename($id, $text($input, 'name')))], $headers);
            $store->delete($id);
            return new Response(200, ['success' => true], $headers);
        } catch (\OutOfBoundsException $error) {
            return new Response(404, ['error' => $error->getMessage()], $headers);
        } catch (\InvalidArgumentException $error) {
            return new Response(422, ['error' => $error->getMessage()], $headers);
        } catch (\Throwable $error) {
            error_log((string) $error);
            return new Response(500, ['error' => 'Storage operation failed. Check server logs.'], $headers);
        }
    }
}
