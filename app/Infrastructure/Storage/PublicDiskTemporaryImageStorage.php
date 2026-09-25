<?php

namespace App\Infrastructure\Storage;

use App\Application\Media\TemporaryImageStorageInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use RuntimeException;

class PublicDiskTemporaryImageStorage implements TemporaryImageStorageInterface
{
    public function upload(UploadedFile $file, string $directory): string
    {
        $filename = $file->hashName();
        $path = $directory.'/'.$filename;

        $encoded = app(ImageManager::class)
            ->decodePath($file->getPathname())
            ->cover(640, 640)
            ->encodeUsingFileExtension(pathinfo($filename, PATHINFO_EXTENSION));

        $stored = Storage::disk('public')->put($path, (string) $encoded);

        if ($stored === false) {
            throw new RuntimeException('The image could not be stored.');
        }

        return config('filesystems.disks.public.url').'/'.$path;
    }
}
