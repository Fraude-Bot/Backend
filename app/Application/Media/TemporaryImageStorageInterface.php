<?php

namespace App\Application\Media;

use Illuminate\Http\UploadedFile;

interface TemporaryImageStorageInterface
{
    public const string PROFILE_PICTURE_DIRECTORY = 'tmp/reports/profile';

    public const string PROOF_DIRECTORY = 'tmp/reports/proofs';

    public const string CACHE_KEY_PREFIX = 'temporary-image-storage:';

    public function upload(UploadedFile $file, string $directory, ?int $width = null, ?int $height = null): string;
    public function uploadProfilePicture(UploadedFile $file): string;
    public function uploadProof(UploadedFile $file): string;
}