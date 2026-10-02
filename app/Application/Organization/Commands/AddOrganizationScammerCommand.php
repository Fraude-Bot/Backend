<?php

namespace App\Application\Organization\Commands;

use App\Models\Organization;
use App\Models\Scammer;

final readonly class AddOrganizationScammerCommand
{
    public function __construct(
        public Organization $organization,
        public Scammer $scammer,
    ) {}
}
