<?php

namespace App\Application\Media;

use Illuminate\Http\UploadedFile;

interface TemporaryImageStorageInterface
{
    public const PROFILE_PICTURE_DIRECTORY = 'tmp/pictures/reports/profile';

    public const CACHE_KEY_PREFIX = 'temporary-image-storage:';

    public function upload(UploadedFile $file, string $directory): string;
    public function uploadProfilePicture(UploadedFile $file): string;
}