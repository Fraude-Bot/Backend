<?php

namespace App\Application\Organization\Commands;

final readonly class SuggestOrganizationsCommand
{
    public function __construct(
        public string $query,
    ) {}
}
