<?php

namespace App\Http\Controllers\Public;

use App\Application\Media\Commands\StoreTemporaryProfilePictureCommand;
use App\Application\Media\Usecases\MediaUsecaseInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreProfilePictureRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class MediaController extends Controller
{
    public function __construct(
        private MediaUsecaseInterface $media,
    ) {}

    public function storeProfilePicture(StoreProfilePictureRequest $request): JsonResponse
    {
        $image = $request->file('image');

        if (! $image instanceof UploadedFile) {
            abort(422, 'The request data is invalid.');
        }

        $path = $this->media->storeTemporaryProfilePicture(new StoreTemporaryProfilePictureCommand($image));

        return response()->json(['path' => $path], 201);
    }
}
