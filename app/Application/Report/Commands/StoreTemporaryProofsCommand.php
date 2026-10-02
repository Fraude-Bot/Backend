<?php

namespace App\Application\Report\Commands;

use Illuminate\Http\UploadedFile;

final readonly class StoreTemporaryProofsCommand
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function __construct(
        public array $files,
    ) {}
}
