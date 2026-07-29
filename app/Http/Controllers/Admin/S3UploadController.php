<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3UploadController extends Controller
{
    private string $root = 'ecommerce';

    private array $imageExts  = ['jpg','jpeg','png','gif','webp','svg'];
    private array $videoExts  = ['mp4','webm','mov','avi','mkv','flv','wmv','m4v'];
    private array $docExts    = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','rtf','odt','ods'];
    private array $archiveExts = ['zip','rar','7z','tar','gz'];

    public function browse(Request $request)
    {
        $path = $this->sanitizePath($request->query('path', $this->root));
        $disk = Storage::disk('s3');

        $folders = collect($disk->directories($path ?: null))
            ->map(function ($dir) {
                return [
                    'name' => basename($dir),
                    'path' => trim($dir, '/'),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $files = collect($disk->files($path ?: null))
            ->filter(fn ($file) => !str_ends_with($file, '/.keep'))
            ->map(function ($file) use ($disk) {
                $name = basename($file);
                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $lastModified = $disk->lastModified($file);
                $size = $disk->size($file);
                $type = $this->fileType($ext);
                $isImage = $type === 'image';

                return [
                    'name' => $name,
                    'path' => '/' . ltrim($file, '/'),
                    'url'  => $this->publicUrl($file) . '?v=' . $lastModified,
                    'type' => $type,
                    'ext'  => $ext,
                    'size' => $size,
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
            'root'        => $this->root,
            'path'        => $path,
            'breadcrumbs' => $this->breadcrumbs($path),
            'folders'     => $folders,
            'files'       => $files,
            'sidebar'     => $rootFolders,
            'prefix'      => $path === '' ? '' : rtrim($path, '/') . '/',
        ]);
    }

    public function createFolder(Request $request)
    {
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
    }

    /**
     * Upload any file. Same-name files are overwritten (replaced).
     * URL includes ?v=timestamp for cache busting.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file'   => 'required|file|max:102400',  // 100 MB
            'folder' => 'nullable|string|max:300',
        ]);

        $folder = $this->sanitizePath($request->input('folder', $this->root));
        $disk   = Storage::disk('s3');

        $file      = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $basename  = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName  = Str::slug($basename) ?: 'file';
        $filename  = $safeName . '.' . $extension;

        $path = $folder . '/' . $filename;

        $disk->put($path, file_get_contents($file->getRealPath()), [
            'ContentType' => $file->getMimeType(),
        ]);

        $version = $disk->lastModified($path);
        $url     = $this->publicUrl($path) . '?v=' . $version;
        $type    = $this->fileType($extension);

        return response()->json([
            'success' => true,
            'url'     => $url,
            'path'    => '/' . ltrim($path, '/'),
            'name'    => $filename,
            'type'    => $type,
            'ext'     => $extension,
        ]);
    }

    /**
     * Delete one or more files from S3.
     */
    public function deleteFiles(Request $request)
    {
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

    private function fileType(string $ext): string
    {
        if (in_array($ext, $this->imageExts))   return 'image';
        if (in_array($ext, $this->videoExts))   return 'video';
        if (in_array($ext, $this->docExts))     return 'document';
        if (in_array($ext, $this->archiveExts)) return 'archive';
        return 'other';
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
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
