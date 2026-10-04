<?php

namespace App\Repositories\Report;

use App\Models\Organization;
use App\Models\Report;
use App\Models\Scammer;

interface ReportRepositoryInterface
{
    public function create(string $title, ?string $description): Report;

    public function attachToOrganization(Organization $organization, Report $report): void;

    public function attachToScammer(Scammer $scammer, Report $report): void;

    public function addProof(Report $report, string $path): int;
}
