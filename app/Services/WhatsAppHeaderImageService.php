<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Meta WhatsApp template headers reject/fail with WebP.
 * Convert remote product images to a public JPEG on S3/R2.
 */
class WhatsAppHeaderImageService
{
    public const MAX_SIDE = 1600;

    public const JPEG_QUALITY = 90;

    /**
     * Return a public HTTPS JPEG/PNG URL suitable for WhatsApp header.
     * Non-WebP URLs are returned unchanged. On conversion failure, returns null
     * so the caller can fall back to the default logo JPEG.
     */
    public function ensureJpegUrl(?string $imageUrl): ?string
    {
        $imageUrl = trim((string) $imageUrl);
        if ($imageUrl === '' || !str_starts_with($imageUrl, 'http')) {
            return null;
        }

        $path = strtolower((string) (parse_url($imageUrl, PHP_URL_PATH) ?: ''));
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        // Already WhatsApp-friendly
        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return $imageUrl;
        }

        // Only convert webp (and odd formats); gif/svg not used as WA headers
        if ($ext !== 'webp') {
            // Unknown format — try converting anyway if Intervention can read it
            Log::info('WhatsApp header image non-jpeg/png; attempting convert', [
                'url' => $imageUrl,
                'ext' => $ext,
            ]);
        }

        $hash = sha1($imageUrl);
        $storageKey = 'ecommerce/whatsapp-headers/' . $hash . '.jpg';

        try {
            $disk = Storage::disk('s3');

            if ($disk->exists($storageKey)) {
                return $this->publicUrl($storageKey);
            }

            $response = Http::timeout(25)
                ->withHeaders(['Accept' => 'image/*,*/*'])
                ->get($imageUrl);

            if (!$response->successful() || empty($response->body())) {
                Log::warning('WhatsApp header: failed to download source image', [
                    'url' => $imageUrl,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $manager = new ImageManager(new Driver());
            $image = $manager->read($response->body());

            if ($image->width() > self::MAX_SIDE || $image->height() > self::MAX_SIDE) {
                $image->scaleDown(self::MAX_SIDE, self::MAX_SIDE);
            }

            $jpeg = (string) $image->toJpeg(self::JPEG_QUALITY);

            $disk->put($storageKey, $jpeg, [
                'ContentType' => 'image/jpeg',
                'CacheControl' => 'public, max-age=31536000',
            ]);

            $public = $this->publicUrl($storageKey);

            Log::info('WhatsApp header: converted WebP to JPEG', [
                'source' => $imageUrl,
                'jpeg' => $public,
            ]);

            return $public;
        } catch (\Throwable $e) {
            Log::error('WhatsApp header: convert failed', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function publicUrl(string $storageKey): string
    {
        $base = rtrim((string) (
            config('filesystems.disks.s3.url')
            ?: config('app.cloud_url')
            ?: 'https://images.dhirago.com'
        ), '/');

        return $base . '/' . ltrim($storageKey, '/');
    }
}
