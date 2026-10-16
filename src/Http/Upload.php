<?php
declare(strict_types=1);
namespace MediaBrowser\Http;

final class Upload
{
    public function __construct(public string $path, public string $name, public int $error = UPLOAD_ERR_OK) {}

    /** Convert native PHP multipart uploads; malformed shapes are rejected. */
    public static function fromGlobals(array $files): array
    {
        $input = $files['files'] ?? [];
        if (!$input) return [];
        if (!isset($input['name'], $input['tmp_name'], $input['error']) || !is_array($input['name']) || !is_array($input['tmp_name']) || !is_array($input['error'])) {
            throw new \InvalidArgumentException('Use the multipart field files[].');
        }
        $uploads = [];
        foreach ($input['name'] as $key => $name) {
            if (!is_string($name) || !is_string($input['tmp_name'][$key] ?? null) || !is_int($input['error'][$key] ?? null)) {
                throw new \InvalidArgumentException('Malformed multipart files.');
            }
            $uploads[] = new self($input['tmp_name'][$key], $name, $input['error'][$key]);
        }
        return $uploads;
    }
}
