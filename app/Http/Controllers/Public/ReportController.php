<?php

namespace App\Http\Controllers\Public;

use App\Application\Report\Commands\SearchReportsByTitleCommand;
use App\Application\Report\Commands\SearchReportsCommand;
use App\Application\Report\Commands\ShowReportCommand;
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
use App\Http\Resources\Public\ReportDetailResource;
use App\Http\Resources\Public\ReportResource;
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

    public function searchByTitle(Request $request): JsonResponse
    {
        $page = $request->input('p', 1);
        $count = $request->input('c', 10);
        $query = $request->input('q');

        if (
            filter_var($page, FILTER_VALIDATE_INT) === false ||
            filter_var($count, FILTER_VALIDATE_INT) === false ||
            (int) $page < 1 ||
            (int) $page > 100000 ||
            (int) $count < 1 ||
            (int) $count > 100
        ) {
            return response()->json(['message' => 'Invalid page or count'], 400);
        }

        $trimmed = is_string($query) ? trim($query) : '';

        $result = $this->reports->searchByTitle(new SearchReportsByTitleCommand(
            $trimmed,
            (int) $page,
            (int) $count,
        ));

        return response()->json([
            'data' => ReportResource::collection($result->items)->resolve(),
            'total' => $result->total,
            'page' => (int) $page,
            'count' => (int) $count,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        if (filter_var($id, FILTER_VALIDATE_INT) === false) {
            return response()->json(['message' => 'Invalid report ID'], 400);
        }

        $report = $this->reports->show(new ShowReportCommand((int) $id));

        if ($report === null) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        return response()->json((new ReportDetailResource($report))->resolve());
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
