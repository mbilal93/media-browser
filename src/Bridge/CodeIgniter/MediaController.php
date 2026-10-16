<?php
declare(strict_types=1);
namespace MediaBrowser\Bridge\CodeIgniter;

use CodeIgniter\Controller;
use MediaBrowser\MediaStore;
use MediaBrowser\Http\Kernel;
use MediaBrowser\Http\Upload;

final class MediaController extends Controller
{
    public function index()
    {
        helper('url');
        $settings = config('MediaBrowser');
        if (!$settings->authorized()) return $this->response->setStatusCode(401)->setJSON(['error' => 'Media management permission is required.']);
        $action = $this->request->getGet('action') ?? 'list';
        if (!is_string($action)) return $this->response->setStatusCode(422)->setJSON(['error' => 'Invalid action.']);
        $uploads = [];
        foreach ($this->request->getFileMultiple('files') ?? [] as $file) {
            if (!$file instanceof \CodeIgniter\HTTP\Files\UploadedFile) return $this->response->setStatusCode(422)->setJSON(['error' => 'Use files[].']);
            $uploads[] = new Upload($file->getTempName(), $file->getClientName(), $file->getError());
        }
        $kernel = new Kernel(new MediaStore($settings->options()), static fn (string $id): string => site_url('media-browser/api') . '?action=file&id=' . rawurlencode($id));
        $result = $kernel->handle($this->request->getMethod(), $action, $this->request->getGet() ?? [], $this->request->getPost() ?? [], $uploads, true,
            ['csrf' => csrf_hash(), 'csrfHeader' => csrf_header(), 'csrfField' => csrf_token(), 'passwordRequired' => false, 'localOnly' => false]);
        $this->response->setHeader(csrf_header(), csrf_hash());
        foreach ($result->headers as $name => $value) $this->response->setHeader($name, $value);
        if ($result->file !== null) {
            $download = $this->response->download($result->file, null);
            $download->setHeader(csrf_header(), csrf_hash());
            foreach ($result->headers as $name => $value) $download->setHeader($name, $value);
            return $download;
        }
        return $this->response->setStatusCode($result->status)->setJSON($result->data);
    }
}
