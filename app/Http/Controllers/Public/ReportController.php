<?php

namespace App\Http\Controllers\Public;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Domain\Scammer\ValueObjects\Clue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreProfilePictureRequest;
use App\Http\Resources\Public\ReportCardResource;
use App\Repositories\Search\SearchRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ReportController extends Controller
{
    public function __construct(
        private SearchRepositoryInterface $searchRepository,
        private TemporaryImageStorageInterface $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $clue = new Clue($request->input('q'));
        $page = max(1, min(100000, (int) $request->input('p', 1)));
        $count = max(1, min(100, (int) $request->input('c', 10)));

        $result = $this->searchRepository->find($clue, $page, $count);

        return response()->json([
            'data' => ReportCardResource::collection($result->items)->resolve(),
            'total' => $result->total,
            'page' => $page,
            'count' => $count,
        ]);
    }

    public function storeTemporaryProfilePicture(StoreProfilePictureRequest $request): JsonResponse
    {
        $image = $request->file('image');

        if (! $image instanceof UploadedFile) {
            abort(422, 'The request data is invalid.');
        }

        $path = $this->storage->uploadProfilePicture($image);

        return response()->json(['path' => $path], 201);
    }
}
