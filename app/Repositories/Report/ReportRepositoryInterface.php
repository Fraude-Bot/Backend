<?php

namespace App\Repositories\Report;

use App\Models\Organization;
use App\Models\Report;

interface ReportRepositoryInterface
{
    public function create(string $title, ?string $description): Report;

    public function attachToOrganization(Organization $organization, Report $report): void;

    public function addProof(Report $report, string $path): int;
}
