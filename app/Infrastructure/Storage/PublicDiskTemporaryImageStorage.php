<?php

namespace App\Infrastructure\Storage;

use App\Application\Media\TemporaryImageStorageInterface;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class PublicDiskTemporaryImageStorage implements TemporaryImageStorageInterface
{
    public function upload(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');
   
        if ($path === false) {
            throw new RuntimeException('The image could not be stored.');
        }

        return config('filesystems.disks.public.url') . '/' . $path;
    }
}
