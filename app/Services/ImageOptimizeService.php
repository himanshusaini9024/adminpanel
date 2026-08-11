<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Convert JPG/PNG uploads to high-quality WebP for Cloudflare R2.
 *
 * Only downscales very large originals (above MAX_DIMENSION). WebP quality
 * is kept high so product photos stay sharp.
 */
class ImageOptimizeService
{
    /** Only shrink if longer side exceeds this (px). */
    public const MAX_DIMENSION = 3200;

    /** Near-lossless look — still typically much smaller than original JPG/PNG. */
    public const WEBP_QUALITY = 95;

    /** Raster formats we convert to WebP. SVG / GIF kept as-is. */
    private array $convertible = ['jpg', 'jpeg', 'png', 'webp'];

    public function shouldOptimize(string $extension): bool
    {
        return in_array(strtolower($extension), $this->convertible, true);
    }

    /**
     * @return array{contents: string, extension: string, mime: string, filename: string}|null
     *         null when the file should be stored without conversion
     */
    public function optimizeToWebp(UploadedFile $file, string $safeBasename): ?array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        if (!$this->shouldOptimize($extension)) {
            return null;
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath());

        // Only shrink oversized images — do not upscale or force a small size
        if ($image->width() > self::MAX_DIMENSION || $image->height() > self::MAX_DIMENSION) {
            $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);
        }

        $encoded = $image->toWebp(self::WEBP_QUALITY);
        $filename = $safeBasename . '.webp';

        return [
            'contents' => (string) $encoded,
            'extension' => 'webp',
            'mime' => 'image/webp',
            'filename' => $filename,
        ];
    }
}
