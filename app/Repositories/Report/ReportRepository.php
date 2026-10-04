<?php

namespace App\Repositories\Report;

use App\Models\Organization;
use App\Models\Report;
use App\Models\ReportProof;
use App\Models\Scammer;
use App\Repositories\Search\SearchCache;

class ReportRepository implements ReportRepositoryInterface
{
    public function create(string $title, ?string $description): Report
    {
        return Report::create([
            'user_id' => null,
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
}
