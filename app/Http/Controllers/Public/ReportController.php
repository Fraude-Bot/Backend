<?php

namespace App\Http\Controllers\Public;

use App\Application\Report\Commands\SearchReportsCommand;
use App\Application\Report\Commands\StoreOrganizationReportCommand;
use App\Application\Report\Commands\StoreTemporaryProfilePictureCommand;
use App\Application\Report\Commands\StoreTemporaryProofsCommand;
use App\Application\Report\Usecases\ReportUsecaseInterface;
use App\Domain\Scammer\ValueObjects\Clue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreOrganizationReportRequest;
use App\Http\Requests\Public\StoreProfilePictureRequest;
use App\Http\Requests\Public\StoreProofRequest;
use App\Http\Resources\Public\ReportCardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ReportController extends Controller
{
    public function __construct(
        private ReportUsecaseInterface $reports,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $clue = new Clue($request->input('q'));
        $page = max(1, min(100000, (int) $request->input('p', 1)));
        $count = max(1, min(100, (int) $request->input('c', 10)));

        $result = $this->reports->search(new SearchReportsCommand($clue, $page, $count));

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

        $path = $this->reports->storeTemporaryProfilePicture(new StoreTemporaryProfilePictureCommand($image));

        return response()->json(['path' => $path], 201);
    }

    public function storeTemporaryProof(StoreProofRequest $request): JsonResponse
    {
        $images = $request->file('images');

        if (! is_array($images) || $images === []) {
            abort(422, 'The request data is invalid.');
        }

        $paths = $this->reports->storeTemporaryProofs(new StoreTemporaryProofsCommand($images));

        return response()->json(['paths' => $paths], 201);
    }

    public function storeOrganization(StoreOrganizationReportRequest $request): JsonResponse
    {
        return response()->json($this->reports->storeOrganization(StoreOrganizationReportCommand::fromValidated($request->validated())), 201);
    }
}
