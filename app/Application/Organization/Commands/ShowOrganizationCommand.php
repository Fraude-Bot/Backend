<?php

namespace App\Application\Organization\Commands;

final readonly class ShowOrganizationCommand
{
    public function __construct(
        public int $id,
    ) {}
}
