<?php

namespace App\Application\Organization\Commands;

use App\Models\Organization;

final readonly class ListOrganizationScammersCommand
{
    public function __construct(
        public Organization $organization,
    ) {}
}
