<?php
declare(strict_types=1);
namespace MediaBrowser;

/** Idempotent example-data seeding using the configured store and table. */
final class DemoSeeder
{
    public function __construct(private array $config) {}

    public function run(): array
    {
        $store = new MediaStore($this->config);
        $mime = $this->sampleMime();
        $extension = $this->config['types'][$mime];
        $temporary = tempnam(sys_get_temp_dir(), 'media-seed-');
        if ($temporary === false) throw new \RuntimeException('Cannot create a temporary seed image.');
        $result = ['created' => 0, 'skipped' => 0, 'images' => []];
        try {
            foreach (['Forest study' => [47, 84, 65], 'Clay study' => [181, 111, 80], 'Golden study' => [207, 169, 89]] as $name => $color) {
                $filename = $name . '.' . $extension;
                $existing = $store->findByName($filename);
                if ($existing) {
                    if (!is_file($store->path($existing))) throw new \RuntimeException('A seed record has missing bytes. Repair or remove that record before seeding again.');
                    $result['skipped']++;
                    $result['images'][] = $store->present($existing);
                    continue;
                }
                $this->writeSample($temporary, $mime, $color);
                clearstatcache(true, $temporary);
                $result['images'][] = $store->add($temporary, $filename, false);
                $result['created']++;
            }
            return $result;
        } finally {
            unlink($temporary);
        }
    }

    private function sampleMime(): string
    {
        foreach (['image/png' => 'imagepng', 'image/jpeg' => 'imagejpeg', 'image/gif' => 'imagegif', 'image/webp' => 'imagewebp'] as $mime => $encoder) {
            if (!isset($this->config['types'][$mime])) continue;
            if (function_exists($encoder) || in_array($mime, ['image/png', 'image/gif'], true)) return $mime;
        }
        throw new \InvalidArgumentException('Seeding needs an allowed image type. Allow PNG/GIF, or enable GD for JPEG/WebP samples.');
    }

    private function writeSample(string $path, string $mime, array $color): void
    {
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(640, 420);
            try {
                imagefill($image, 0, 0, imagecolorallocate($image, ...$color));
                imagefilledellipse($image, 320, 210, 260, 260, imagecolorallocate($image, 237, 232, 213));
                imagestring($image, 5, 262, 205, 'MEDIA / DEMO', imagecolorallocate($image, ...$color));
                $encoder = ['image/png' => 'imagepng', 'image/jpeg' => 'imagejpeg', 'image/gif' => 'imagegif', 'image/webp' => 'imagewebp'][$mime];
                if (!$encoder($image, $path)) throw new \RuntimeException('Cannot encode a seed image.');
            } finally {
                imagedestroy($image);
            }
        } else {
            $bytes = $mime === 'image/gif'
                ? 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
                : 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
            if (file_put_contents($path, base64_decode($bytes, true)) === false) throw new \RuntimeException('Cannot write a seed image.');
        }
    }
}
