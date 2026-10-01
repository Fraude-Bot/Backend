<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\ContactResource;
use App\Http\Resources\Public\OrganizationResource;
use App\Http\Resources\Public\ReportResource;
use App\Repositories\Organization\OrganizationRepositoryInterface;
use App\Repositories\Suggest\PublicSuggestRepository;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(
        private OrganizationRepositoryInterface $organizationRepository,
        private PublicSuggestRepository $suggestRepository,
    ) {
    }

    public function show(Request $request, string $id)
    {
        if (filter_var($id, FILTER_VALIDATE_INT) === false) {
            return response()->json(['message' => 'Invalid organization ID'], 400);
        }

        $organization = $this->organizationRepository->findOrganizationById((int) $id);

        if (!$organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        return response()->json((new OrganizationResource($organization))->resolve());
    }

    public function calendar(Request $request, string $id, string $year)
    {
        if (
            filter_var($id, FILTER_VALIDATE_INT) === false
            || filter_var($year, FILTER_VALIDATE_INT) === false
            || (int) $year < 2000
            || (int) $year > now()->year + 1
        ) {
            return response()->json(['message' => 'Invalid organization ID or year'], 400);
        }

        $calendar = $this->organizationRepository->findCalendarByOrganizationIdAndYear((int) $id, (int) $year);

        if (!$calendar) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        return response()->json($calendar);
    }

    public function contacts(Request $request, string $id)
    {
        $page = $request->input('p', 1);
        $count = $request->input('c', 10);
        $platform = $request->input('platform', null);

        if ($platform) {
            $platform = strtolower($platform);
        }

        if (
            filter_var($id, FILTER_VALIDATE_INT) === false ||
            filter_var($page, FILTER_VALIDATE_INT) === false ||
            filter_var($count, FILTER_VALIDATE_INT) === false ||
            (int) $page < 1 ||
            (int) $page > 100000 ||
            (int) $count < 1 ||
            (int) $count > 100
        ) {
            return response()->json(['message' => 'Invalid organization ID, page or count'], 400);
        }

        $contacts = $this->organizationRepository->findPaginatedContactsById((int) $id, (int) $page, (int) $count, $platform);

        if (!$contacts) {
            return response()->json(['message' => 'Organization contacts not found'], 404);
        }

        return response()->json([
            'data' => ContactResource::collection($contacts->items)->resolve(),
            'total' => $contacts->total,
            'page' => (int) $page,
            'count' => (int) $count,
        ]);
    }

    public function reports(Request $request, string $id)
    {
        $page = $request->input('p', 1);
        $count = $request->input('c', 10);

        if (
            filter_var($id, FILTER_VALIDATE_INT) === false ||
            filter_var($page, FILTER_VALIDATE_INT) === false ||
            filter_var($count, FILTER_VALIDATE_INT) === false ||
            (int) $page < 1 ||
            (int) $page > 100000 ||
            (int) $count < 1 ||
            (int) $count > 100
        ) {
            return response()->json(['message' => 'Invalid organization ID, page or count'], 400);
        }

        $reports = $this->organizationRepository->findPaginatedReportsById((int) $id, (int) $page, (int) $count);

        if (!$reports) {
            return response()->json(['message' => 'Organization reports not found'], 404);
        }

        return response()->json([
            'data' => ReportResource::collection($reports->items)->resolve(),
            'total' => $reports->total,
            'page' => (int) $page,
            'count' => (int) $count,
        ]);
    }

    public function map(Request $request, string $id)
    {
        if (
            filter_var($id, FILTER_VALIDATE_INT) === false ||
            (int) $id < 1
        ) {
            return response()->json(['message' => 'Invalid organization ID'], 400);
        }

        $map = $this->organizationRepository->findMapById((int) $id);

        if (!$map) {
            return response()->json(['message' => 'Organization map not found'], 404);
        }

        if ($map->isEmpty()) {
            return response()->json(['message' => 'Organization map is empty'], 404);
        }

        return response()->json($map->toArray());
    }

    public function suggest(Request $request)
    {
        $query = $request->input('q');

        return response()->json($this->suggestRepository->organizations(is_string($query) ? $query : ''));
    }
}
