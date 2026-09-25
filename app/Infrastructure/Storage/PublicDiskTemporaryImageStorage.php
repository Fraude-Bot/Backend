<?php

namespace App\Infrastructure\Storage;

use App\Application\Media\TemporaryImageStorageInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use RuntimeException;

class PublicDiskTemporaryImageStorage implements TemporaryImageStorageInterface
{
    public function upload(UploadedFile $file, string $directory): string
    {
        $key = TemporaryImageStorageInterface::CACHE_KEY_PREFIX.md5($file->getContent());
        $cached = Cache::get($key);

        if (is_string($cached) && $this->storedFileExists($cached)) {
            return $cached;
        }

        $filename = $file->hashName();
        $path = trim($directory, '/').'/'.$filename;

        $encoded = app(ImageManager::class)
            ->decodePath($file->getPathname())
            ->cover(640, 640)
            ->encodeUsingFileExtension(pathinfo($filename, PATHINFO_EXTENSION));

        $stored = Storage::disk('public')->put($path, (string) $encoded);

        if ($stored === false) {
            throw new RuntimeException('The image could not be stored.');
        }

        $url = config('filesystems.disks.public.url').'/'.$path;
        Cache::forever($key, $url);

        return $url;
    }

    public function uploadProfilePicture(UploadedFile $file): string
    {
        return $this->upload($file, TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY);
    }

    private function storedFileExists(string $url): bool
    {
        $prefix = rtrim((string) config('filesystems.disks.public.url'), '/').'/';

        if (! str_starts_with($url, $prefix)) {
            return false;
        }

        return Storage::disk('public')->exists(substr($url, strlen($prefix)));
    }
}
