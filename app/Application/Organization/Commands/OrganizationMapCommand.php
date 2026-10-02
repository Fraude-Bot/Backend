<?php

namespace App\Application\Organization\Commands;

final readonly class OrganizationMapCommand
{
    public function __construct(
        public int $id,
    ) {}
}
