<?php

namespace App\Infrastructure\Storage;

use App\Application\Media\TemporaryImageStorageInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;

class PublicDiskTemporaryImageStorage implements TemporaryImageStorageInterface
{
    public function upload(UploadedFile $file, string $directory, ?int $width = null, ?int $height = null): string
    {
        if (($width === null) !== ($height === null)) {
            throw new InvalidArgumentException('Crop width and height must both be set or both be null.');
        }

        $key = $this->cacheKey($file, $directory, $width, $height);
        $cached = Cache::get($key);

        if (is_string($cached) && $this->storedFileExists($cached)) {
            return $cached;
        }

        $filename = $file->hashName();
        $path = trim($directory, '/').'/'.$filename;
        $contents = $width === null
            ? $file->getContent()
            : (string) app(ImageManager::class)
                ->decodePath($file->getPathname())
                ->cover($width, $height)
                ->encodeUsingFileExtension(pathinfo($filename, PATHINFO_EXTENSION));

        $stored = Storage::disk('public')->put($path, $contents);

        if ($stored === false) {
            throw new RuntimeException('The image could not be stored.');
        }

        $url = config('filesystems.disks.public.url').'/'.$path;
        Cache::forever($key, $url);

        return $url;
    }

    public function uploadProfilePicture(UploadedFile $file): string
    {
        return $this->upload($file, TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY, 640, 640);
    }

    public function uploadProof(UploadedFile $file): string
    {
        return $this->upload($file, TemporaryImageStorageInterface::PROOF_DIRECTORY);
    }

    private function cacheKey(UploadedFile $file, string $directory, ?int $width, ?int $height): string
    {
        $variant = $width === null ? 'original' : $width.'x'.$height;

        return TemporaryImageStorageInterface::CACHE_KEY_PREFIX.md5(trim($directory, '/').':'.$variant.':'.$file->getContent());
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
