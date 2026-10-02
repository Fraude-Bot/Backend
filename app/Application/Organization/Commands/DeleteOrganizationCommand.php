<?php

namespace App\Application\Organization\Commands;

use App\Models\Organization;

final readonly class DeleteOrganizationCommand
{
    public function __construct(
        public Organization $organization,
    ) {}
}
