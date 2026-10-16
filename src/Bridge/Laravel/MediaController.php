<?php
declare(strict_types=1);
namespace MediaBrowser\Bridge\Laravel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use MediaBrowser\MediaStore;
use MediaBrowser\Http\Kernel;
use MediaBrowser\Http\Upload;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MediaController
{
    public function __invoke(Request $request, MediaStore $store)
    {
        Gate::authorize(config('media-browser.ability', 'manage-media'));
        $action = $request->query('action', 'list');
        if (!is_string($action)) return response()->json(['error' => 'Invalid action.'], 422);
        $uploads = [];
        foreach ((array) $request->file('files', []) as $file) {
            if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) return response()->json(['error' => 'Use files[].'], 422);
            $uploads[] = new Upload($file->getPathname(), $file->getClientOriginalName(), $file->getError());
        }
        $kernel = new Kernel($store, static fn (string $id): string => route('media-browser.api', ['action' => 'file', 'id' => $id]));
        $result = $kernel->handle($request->method(), $action, $request->query->all(), $request->request->all(), $uploads, true,
            ['csrf' => csrf_token(), 'csrfHeader' => 'X-CSRF-TOKEN', 'passwordRequired' => false, 'localOnly' => false]);
        if ($result->file !== null) return new BinaryFileResponse($result->file, $result->status, $result->headers);
        return response()->json($result->data, $result->status, $result->headers);
    }
}
