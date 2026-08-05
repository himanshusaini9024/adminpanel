<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3UploadController extends Controller
{
    private string $root = 'ecommerce';

    private array $imageExts   = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    private array $videoExts   = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'm4v'];
    private array $docExts     = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods'];
    private array $archiveExts = ['zip', 'rar', '7z', 'tar', 'gz'];

    public function browse(Request $request)
    {
        try {
            $this->assertS3Configured();

            $path = $this->sanitizePath($request->query('path', $this->root));
            $disk = Storage::disk('s3');

            // One ListObjects call — avoid per-file lastModified()/size() round-trips
            // Flysystem v3 returns StorageAttributes objects (not arrays)
            $listing = collect(iterator_to_array($disk->listContents($path === '' ? '' : $path, false)));

            $folders = $listing
                ->filter(fn ($item) => $item->isDir())
                ->map(function ($item) {
                    $dir = trim($item->path(), '/');
                    return [
                        'name' => basename($dir),
                        'path' => $dir,
                    ];
                })
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            $files = $listing
                ->filter(function ($item) {
                    if (!$item->isFile()) {
                        return false;
                    }
                    $filePath = $item->path();
                    return $filePath !== '' && !str_ends_with($filePath, '/.keep');
                })
                ->map(function ($item) {
                    $file = trim($item->path(), '/');
                    $name = basename($file);
                    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $lastModified = (int) ($item->lastModified() ?: time());
                    $size = (int) ($item->fileSize() ?: 0);

                    return [
                        'name'       => $name,
                        'path'       => '/' . $file,
                        'url'        => $this->publicUrl($file) . '?v=' . $lastModified,
                        'type'       => $this->fileType($ext),
                        'ext'        => $ext,
                        'size'       => $size,
                        'size_human' => $this->humanSize($size),
                    ];
                })
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            $rootFolders = collect($disk->directories($this->root))
                ->map(function ($dir) {
                    return [
                        'name' => basename($dir),
                        'path' => trim($dir, '/'),
                    ];
                })
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            return response()->json([
                'success'     => true,
                'root'        => $this->root,
                'path'        => $path,
                'breadcrumbs' => $this->breadcrumbs($path),
                'folders'     => $folders,
                'files'       => $files,
                'sidebar'     => $rootFolders,
                'prefix'      => $path === '' ? '' : rtrim($path, '/') . '/',
            ]);
        } catch (\Throwable $e) {
            Log::error('S3 browse failed: ' . $e->getMessage(), [
                'path' => $request->query('path'),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'folders' => [],
                'files'   => [],
                'sidebar' => [],
                'breadcrumbs' => [],
                'root' => $this->root,
                'path' => $this->root,
            ], 500);
        }
    }

    public function createFolder(Request $request)
    {
        try {
            $this->assertS3Configured();

            $request->validate([
                'path' => 'nullable|string|max:300',
                'name' => 'required|string|max:100',
            ]);

            $parent = $this->sanitizePath($request->input('path', $this->root));
            $name = trim($request->input('name'));
            $name = preg_replace('/[^a-zA-Z0-9 _\-\.]/', '', $name);
            $name = trim(preg_replace('/\s+/', '-', $name), '-._');

            if ($name === '') {
                return response()->json(['message' => 'Invalid folder name'], 422);
            }

            $folderPath = trim($parent . '/' . $name, '/');
            Storage::disk('s3')->put($folderPath . '/.keep', '');

            return response()->json([
                'success' => true,
                'path'    => $folderPath,
                'name'    => basename($folderPath),
            ]);
        } catch (\Throwable $e) {
            Log::error('S3 createFolder failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload any file. Same-name files are overwritten (replaced).
     * URL includes ?v=timestamp for cache busting.
     */
    public function upload(Request $request)
    {
        try {
            $this->assertS3Configured();

            $request->validate([
                'file'   => 'required|file|max:102400', // 100 MB
                'folder' => 'nullable|string|max:300',
                'prefix' => 'nullable|string|max:20',
            ]);

            $folder = $this->sanitizePath($request->input('folder', $this->root));
            $disk   = Storage::disk('s3');

            $file      = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $basename  = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeName  = Str::slug($basename) ?: 'file';

            // desk- / mob- prefix when uploading desktop vs mobile image into same folder
            $prefix = strtolower(trim((string) $request->input('prefix', '')));
            $prefix = preg_replace('/[^a-z0-9\-]+/', '', $prefix) ?: '';
            if ($prefix !== '' && !str_starts_with($safeName, $prefix . '-')) {
                $safeName = $prefix . '-' . $safeName;
            }

            $filename  = $safeName . '.' . $extension;

            $path = $folder . '/' . $filename;

            $disk->put($path, file_get_contents($file->getRealPath()), [
                'ContentType' => $file->getMimeType(),
            ]);

            $version = time();
            try {
                $version = $disk->lastModified($path);
            } catch (\Throwable $e) {
                // ignore
            }

            $url  = $this->publicUrl($path) . '?v=' . $version;
            $type = $this->fileType($extension);

            return response()->json([
                'success' => true,
                'url'     => $url,
                'path'    => '/' . ltrim($path, '/'),
                'name'    => $filename,
                'type'    => $type,
                'ext'     => $extension,
            ]);
        } catch (\Throwable $e) {
            Log::error('S3 upload failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete one or more files from S3.
     */
    public function deleteFiles(Request $request)
    {
        try {
            $this->assertS3Configured();

            $request->validate([
                'paths'   => 'required|array|min:1',
                'paths.*' => 'required|string|max:500',
            ]);

            $disk    = Storage::disk('s3');
            $deleted = [];
            $errors  = [];

            foreach ($request->input('paths') as $rawPath) {
                $clean = ltrim(str_replace('\\', '/', (string) $rawPath), '/');

                if (!str_starts_with($clean, $this->root . '/') || str_ends_with($clean, '/.keep')) {
                    $errors[] = $rawPath;
                    continue;
                }

                if ($disk->exists($clean)) {
                    $disk->delete($clean);
                    $deleted[] = $rawPath;
                } else {
                    $errors[] = $rawPath;
                }
            }

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'errors'  => $errors,
            ]);
        } catch (\Throwable $e) {
            Log::error('S3 delete failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $request->merge(['path' => $request->query('folder', $this->root)]);
        return $this->browse($request);
    }

    /**
     * Full-page file manager view.
     */
    public function page()
    {
        return view('backend.layouts.file-manager');
    }

    // ─── helpers ────────────────────────────────────────────────────

    private function assertS3Configured(): void
    {
        $key    = config('filesystems.disks.s3.key');
        $secret = config('filesystems.disks.s3.secret');
        $bucket = config('filesystems.disks.s3.bucket');

        if (empty($key) || empty($secret) || empty($bucket)) {
            throw new \RuntimeException(
                'AWS S3 is not configured. Set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET and AWS_URL in .env, then run: php artisan config:clear'
            );
        }
    }

    private function fileType(string $ext): string
    {
        if (in_array($ext, $this->imageExts, true)) {
            return 'image';
        }
        if (in_array($ext, $this->videoExts, true)) {
            return 'video';
        }
        if (in_array($ext, $this->docExts, true)) {
            return 'document';
        }
        if (in_array($ext, $this->archiveExts, true)) {
            return 'archive';
        }
        return 'other';
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1073741824, 2) . ' GB';
    }

    private function sanitizePath(?string $path): string
    {
        $path = str_replace('\\', '/', (string) $path);
        $path = trim($path, '/');
        $path = preg_replace('#/+#', '/', $path);
        $parts = array_values(array_filter(explode('/', $path), function ($part) {
            return $part !== '' && $part !== '.' && $part !== '..';
        }));

        if (empty($parts)) {
            return $this->root;
        }

        if ($parts[0] !== $this->root) {
            array_unshift($parts, $this->root);
        }

        $clean = [];
        foreach ($parts as $part) {
            $part = preg_replace('/[^a-zA-Z0-9 _\-\.]/', '', $part);
            $part = trim($part);
            if ($part !== '') {
                $clean[] = $part;
            }
        }

        return implode('/', $clean) ?: $this->root;
    }

    private function breadcrumbs(string $path): array
    {
        $parts = $path === '' ? [] : explode('/', $path);
        $crumbs = [];
        $accum = '';

        foreach ($parts as $part) {
            $accum = ltrim($accum . '/' . $part, '/');
            $crumbs[] = [
                'name' => $part === $this->root ? 'Data' : $part,
                'path' => $accum,
            ];
        }

        return $crumbs;
    }

    private function publicUrl(string $path): string
    {
        $path = ltrim($path, '/');
        $base = rtrim(config('filesystems.disks.s3.url') ?: config('app.cloud_url'), '/');

        if ($base) {
            return $base . '/' . $path;
        }

        return Storage::disk('s3')->url($path);
    }
}
