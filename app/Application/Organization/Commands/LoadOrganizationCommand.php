<?php

namespace App\Application\Organization\Commands;

use App\Models\Organization;

final readonly class LoadOrganizationCommand
{
    public function __construct(
        public Organization $organization,
    ) {}
}
