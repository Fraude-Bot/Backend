<?php

namespace App\Repositories\Report;

use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Organization;
use App\Models\Report;
use App\Models\ReportProof;
use App\Models\Scammer;
use App\Repositories\Search\SearchCache;
use Illuminate\Support\Facades\Cache;

class ReportRepository implements ReportRepositoryInterface
{
    private const int CACHE_TTL_SECONDS = 3600;

    public function create(string $title, ?string $description, int $userId): Report
    {
        return Report::create([
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'is_active' => true,
        ]);
    }

    public function attachToOrganization(Organization $organization, Report $report): void
    {
        $organization->reports()->syncWithoutDetaching([$report->id]);
    }

    public function attachToScammer(Scammer $scammer, Report $report): void
    {
        $scammer->reports()->syncWithoutDetaching([$report->id]);
    }

    public function addProof(Report $report, string $path): int
    {
        return ReportProof::query()->create([
            'report_id' => $report->id,
            'path' => $path,
        ])->id;
    }

    public function attachProduct(Report $report, int $productId): void
    {
        $report->products()->syncWithoutDetaching([$productId]);
        SearchCache::invalidate();
    }

    public function findActiveById(int $id): ?Report
    {
        return Cache::remember(
            SearchCache::key("public:report:{$id}"),
            self::CACHE_TTL_SECONDS,
            fn () => Report::query()
                ->where('is_active', true)
                ->with([
                    'proofs' => fn ($query) => $query->orderBy('id'),
                    'products' => fn ($query) => $query->orderBy('name'),
                ])
                ->find($id),
        );
    }

    public function findPaginatedByTitleSearch(string $query, int $page, int $count): PaginatedResult
    {
        if ($query === '') {
            return PaginatedResult::empty();
        }

        $normalizedQuery = strtolower($query);
        $cacheKey = SearchCache::key("public:reports:title:{$normalizedQuery}:{$page}:{$count}");

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($query, $page, $count): PaginatedResult {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);

            $baseQuery = Report::query()
                ->where('is_active', true)
                ->whereRaw("title LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orderByDesc('updated_at')
                ->orderByDesc('id');

            $total = (clone $baseQuery)->count();
            $items = $baseQuery->forPage($page, $count)->get();

            return new PaginatedResult($items, $total);
        });
    }
}
