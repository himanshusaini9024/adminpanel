<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Optimize large JPG/PNG (and WebP) uploads for Cloudflare R2 / CDN delivery.
 *
 * Flow: original → max 1600px → WebP q80 → discard original.
 */
class ImageOptimizeService
{
    public const MAX_DIMENSION = 1600;
    public const WEBP_QUALITY = 80;

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

        // Shrink only if larger than max on either side; keep aspect ratio
        $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

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
