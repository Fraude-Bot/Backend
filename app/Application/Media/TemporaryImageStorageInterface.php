<?php

namespace App\Application\Media;

use Illuminate\Http\UploadedFile;

interface TemporaryImageStorageInterface
{
    public function upload(UploadedFile $file, string $directory): string;
}