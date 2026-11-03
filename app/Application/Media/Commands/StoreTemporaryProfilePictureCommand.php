<?php

namespace App\Application\Media\Commands;

use Illuminate\Http\UploadedFile;

final readonly class StoreTemporaryProfilePictureCommand
{
    public function __construct(
        public UploadedFile $file,
    ) {}
}
