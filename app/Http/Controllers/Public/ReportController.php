<?php

namespace App\Http\Controllers\Public;

use App\Application\Report\Commands\SearchReportsCommand;
use App\Application\Report\Commands\StoreOrganizationReportCommand;
use App\Application\Report\Commands\StoreReportForOrganizationCommand;
use App\Application\Report\Commands\StoreReportForScammerCommand;
use App\Application\Report\Commands\StoreScammerReportCommand;
use App\Application\Report\Commands\StoreTemporaryProofsCommand;
use App\Application\Report\Usecases\ReportUsecaseInterface;
use App\Domain\Scammer\ValueObjects\Clue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreAttachedReportRequest;
use App\Http\Requests\Public\StoreOrganizationReportRequest;
use App\Http\Requests\Public\StoreProofRequest;
use App\Http\Requests\Public\StoreScammerReportRequest;
use App\Http\Resources\Public\ReportCardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function storeScammer(StoreScammerReportRequest $request): JsonResponse
    {
        return response()->json($this->reports->storeScammer(StoreScammerReportCommand::fromValidated($request->validated())), 201);
    }

    public function storeForScammer(StoreAttachedReportRequest $request, string $id): JsonResponse
    {
        if (filter_var($id, FILTER_VALIDATE_INT) === false) {
            return response()->json(['message' => 'Invalid scammer ID'], 400);
        }

        $created = $this->reports->storeForScammer(
            StoreReportForScammerCommand::fromValidated((int) $id, $request->validated()),
        );

        if ($created === null) {
            return response()->json(['message' => 'Scammer not found'], 404);
        }

        return response()->json($created, 201);
    }

    public function storeForOrganization(StoreAttachedReportRequest $request, string $id): JsonResponse
    {
        if (filter_var($id, FILTER_VALIDATE_INT) === false) {
            return response()->json(['message' => 'Invalid organization ID'], 400);
        }

        $created = $this->reports->storeForOrganization(
            StoreReportForOrganizationCommand::fromValidated((int) $id, $request->validated()),
        );

        if ($created === null) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        return response()->json($created, 201);
    }
}
