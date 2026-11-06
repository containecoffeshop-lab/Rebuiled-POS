<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OptimizedImageUpload
{
    public function store(UploadedFile $upload, string $directory, int $maxDimension, string $field = 'image'): string
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages([
                $field => 'Pemrosesan gambar belum aktif di server. Aktifkan ekstensi PHP GD lalu coba kembali.',
            ]);
        }

        $contents = file_get_contents($upload->getRealPath());
        $source = $contents === false ? false : @imagecreatefromstring($contents);

        if ($source === false) {
            throw ValidationException::withMessages([$field => 'File gambar tidak dapat diproses.']);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $format = function_exists('imagewebp') ? 'webp' : ($directory === 'store' ? 'png' : 'jpg');
        $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $optimized = imagecreatetruecolor($width, $height);
        if ($optimized === false) {
            imagedestroy($source);
            throw new RuntimeException('Ruang memori tidak cukup untuk mengolah gambar.');
        }
        $supportsAlpha = $format !== 'jpg' && in_array($upload->getMimeType(), ['image/png', 'image/webp'], true);

        if ($supportsAlpha) {
            imagealphablending($optimized, false);
            imagesavealpha($optimized, true);
            $transparent = imagecolorallocatealpha($optimized, 0, 0, 0, 127);
            imagefilledrectangle($optimized, 0, 0, $width, $height, $transparent);
        } else {
            $background = imagecolorallocate($optimized, 255, 255, 255);
            imagefilledrectangle($optimized, 0, 0, $width, $height, $background);
        }

        imagecopyresampled($optimized, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        imagedestroy($source);

        $relativePath = "uploads/{$directory}/".bin2hex(random_bytes(16)).".{$format}";
        $absolutePath = public_path($relativePath);
        $temporaryPath = $absolutePath.'.tmp-'.bin2hex(random_bytes(8));

        try {
            File::ensureDirectoryExists(dirname($absolutePath));
            $saved = match ($format) {
                'webp' => imagewebp($optimized, $temporaryPath, 82),
                'jpg' => imagejpeg($optimized, $temporaryPath, 82),
                default => imagepng($optimized, $temporaryPath, 9),
            };

            if (! $saved || ! rename($temporaryPath, $absolutePath)) {
                throw new RuntimeException('Gambar hasil kompresi tidak dapat disimpan.');
            }
        } finally {
            imagedestroy($optimized);

            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        return $relativePath;
    }

    public function delete(?string $relativePath): void
    {
        if (! $relativePath || ! preg_match('#^uploads/(?:menu|store)/[a-f0-9]{32}\.(?:webp|jpg|png)$#', $relativePath)) {
            return;
        }

        $path = public_path($relativePath);

        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException('File gambar lama tidak dapat dihapus.');
        }
    }
}
