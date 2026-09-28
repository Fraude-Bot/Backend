<?php

namespace App\Infrastructure\Storage;

use App\Application\Media\ImageRejectedException;
use App\Application\Media\TemporaryImageStorageInterface;
use App\Infrastructure\Media\AntiNSFWAnalyzer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;

class PublicDiskTemporaryImageStorage implements TemporaryImageStorageInterface
{
    public function __construct(private AntiNSFWAnalyzer $antiNsfwAnalyzer) {}

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
        $original = $file->getContent();

        if (Storage::disk('raw')->put($path, $original) === false) {
            throw new RuntimeException('The image could not be stored.');
        }

        if (! $this->antiNsfwAnalyzer->passes($original)) {
            if (Storage::disk('quarantine')->put($path, $original) === false) {
                throw new RuntimeException('The image could not be stored.');
            }

            Storage::disk('raw')->delete($path);

            throw new ImageRejectedException;
        }

        try {
            $contents = $width === null
                ? $original
                : (string) app(ImageManager::class)
                    ->decodePath($file->getPathname())
                    ->cover($width, $height)
                    ->encodeUsingFileExtension(pathinfo($filename, PATHINFO_EXTENSION));

            if (Storage::disk('public')->put($path, $contents) === false) {
                throw new RuntimeException('The image could not be stored.');
            }
        } finally {
            Storage::disk('raw')->delete($path);
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

    public function copyPublishedTemporary(string $location, string $sourceDirectory, string $destinationDirectory): string
    {
        $relative = $this->publicRelativePath($location);
        $sourceDirectory = trim($sourceDirectory, '/');

        if ($relative === null || ! $this->isDirectChild($relative, $sourceDirectory) || ! Storage::disk('public')->exists($relative)) {
            throw new InvalidArgumentException('The image is not a published temporary file.');
        }

        $extension = pathinfo($relative, PATHINFO_EXTENSION);
        $filename = Str::uuid()->toString().($extension === '' ? '' : '.'.strtolower($extension));
        $destination = trim($destinationDirectory, '/').'/'.$filename;

        if (Storage::disk('public')->copy($relative, $destination) === false) {
            throw new RuntimeException('The image could not be stored.');
        }

        return $destination;
    }

    public function deletePublished(string $relativePath): void
    {
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '' || str_contains($relativePath, '..') || ! str_starts_with($relativePath, 'reports/')) {
            return;
        }

        Storage::disk('public')->delete($relativePath);
    }

    private function cacheKey(UploadedFile $file, string $directory, ?int $width, ?int $height): string
    {
        $variant = $width === null ? 'original' : $width.'x'.$height;

        return TemporaryImageStorageInterface::CACHE_KEY_PREFIX.md5(trim($directory, '/').':'.$variant.':'.$file->getContent());
    }

    private function storedFileExists(string $url): bool
    {
        $relative = $this->publicRelativePath($url);

        return $relative !== null && Storage::disk('public')->exists($relative);
    }

    private function publicRelativePath(string $location): ?string
    {
        $location = trim($location);

        if ($location === '' || str_contains($location, '..') || str_contains($location, '\\')) {
            return null;
        }

        $publicUrl = rtrim((string) config('filesystems.disks.public.url'), '/');
        $prefixes = [$publicUrl.'/'];
        $urlPath = parse_url($publicUrl, PHP_URL_PATH);

        if (is_string($urlPath) && $urlPath !== '' && $urlPath !== '/') {
            $prefixes[] = rtrim($urlPath, '/').'/';
        }

        foreach ($prefixes as $prefix) {
            if (! str_starts_with($location, $prefix)) {
                continue;
            }

            $relative = ltrim(substr($location, strlen($prefix)), '/');

            return $relative === '' ? null : $relative;
        }

        return null;
    }

    private function isDirectChild(string $relative, string $directory): bool
    {
        $prefix = $directory.'/';

        if (! str_starts_with($relative, $prefix)) {
            return false;
        }

        $name = substr($relative, strlen($prefix));

        return $name !== '' && ! str_contains($name, '/');
    }
}
