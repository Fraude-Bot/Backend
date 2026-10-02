<?php

namespace App\Application\Organization\Commands;

final readonly class RestoreOrganizationCommand
{
    public function __construct(
        public int $id,
    ) {}
}
