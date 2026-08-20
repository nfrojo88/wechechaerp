<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload a file to the centralized public/uploads/{folder} directory.
     *
     * @param UploadedFile|string $file
     * @param string $folder e.g. 'employees', 'guarantee_letters', 'education', 'receipts', 'assets'
     * @param bool $tryCloudinary Whether to attempt Cloudinary upload first if configured
     * @return string Returns the stored relative path e.g. 'uploads/employees/17234823_abc123.jpg' or Cloudinary URL
     */
    public static function upload($file, string $folder = 'general', bool $tryCloudinary = true): string
    {
        if (!$file) {
            return '';
        }

        // Try Cloudinary upload if requested
        if ($tryCloudinary) {
            try {
                $cloudinary = app(\App\Services\CloudinaryService::class);
                $cloudUrl = $cloudinary->uploadToCloudinaryOnly($file, $folder);
                if (!empty($cloudUrl) && Str::startsWith($cloudUrl, ['http://', 'https://'])) {
                    return $cloudUrl;
                }
            } catch (\Throwable $e) {
                Log::warning('FileUploadService: Cloudinary upload bypassed, falling back to centralized local uploads. ' . $e->getMessage());
            }
        }

        // Centralized Local Storage: public/uploads/{folder}/
        try {
            $folderName = trim(str_replace(['\\', '..'], '/', $folder), '/');
            $targetDir = public_path('uploads/' . $folderName);

            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            if ($file instanceof UploadedFile) {
                $extension = strtolower($file->getClientOriginalExtension()) ?: 'png';
                $safeName = time() . '_' . Str::random(10) . '.' . $extension;
                $file->move($targetDir, $safeName);

                return 'uploads/' . $folderName . '/' . $safeName;
            } elseif (is_string($file) && file_exists($file)) {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION)) ?: 'png';
                $safeName = time() . '_' . Str::random(10) . '.' . $extension;
                $destination = $targetDir . '/' . $safeName;
                copy($file, $destination);

                return 'uploads/' . $folderName . '/' . $safeName;
            }
        } catch (\Throwable $e) {
            Log::error('FileUploadService Local Upload Error: ' . $e->getMessage());
        }

        return '';
    }

    /**
     * Get the accessible public URL for any uploaded file or image.
     * Works seamlessly for Cloudinary URLs, public/uploads, and legacy public/storage files.
     *
     * @param string|null $path
     * @param string|null $fallback Default placeholder image URL or null
     * @return string|null
     */
    public static function url(?string $path, ?string $fallback = null): ?string
    {
        if (empty($path)) {
            return $fallback;
        }

        $path = trim($path);

        // 1. External or Cloudinary Full URL
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        // Clean leading slashes
        $cleanPath = ltrim($path, '/');

        // Helper to format URL using current request host or asset() fallback
        $formatUrl = function (string $relPath): string {
            $relPath = ltrim($relPath, '/');
            if (!app()->runningInConsole() && request() && request()->hasHeader('host')) {
                $base = rtrim(request()->getBasePath() ?? '', '/');
                $host = method_exists(request(), 'getSchemeAndHttpHost')
                    ? request()->getSchemeAndHttpHost()
                    : (request()->getScheme() . '://' . request()->getHttpHost());
                return $host . ($base ? $base : '') . '/' . $relPath;
            }
            return asset($relPath);
        };

        // 2. Centralized public/uploads directory
        if (Str::startsWith($cleanPath, 'uploads/')) {
            return $formatUrl($cleanPath);
        }

        if (Str::startsWith($cleanPath, 'public/uploads/')) {
            return $formatUrl(substr($cleanPath, 7)); // strip 'public/'
        }

        // 3. If file exists in public/uploads/
        if (file_exists(public_path('uploads/' . $cleanPath))) {
            return $formatUrl('uploads/' . $cleanPath);
        }

        // 4. Legacy storage link support (storage/...)
        if (Str::startsWith($cleanPath, 'storage/')) {
            return $formatUrl($cleanPath);
        }

        // 5. Check if it exists in public/ direct
        if (file_exists(public_path($cleanPath))) {
            return $formatUrl($cleanPath);
        }

        // Fallback: Default to uploads/ path
        return $formatUrl('uploads/' . $cleanPath);
    }

    /**
     * Delete an uploaded file from the centralized storage.
     *
     * @param string|null $path
     * @return bool
     */
    public static function delete(?string $path): bool
    {
        if (empty($path) || Str::startsWith($path, ['http://', 'https://'])) {
            return false;
        }

        $cleanPath = ltrim(str_replace('public/', '', $path), '/');
        $fullPath = public_path($cleanPath);

        if (file_exists($fullPath) && is_file($fullPath)) {
            return @unlink($fullPath);
        }

        return false;
    }
}
