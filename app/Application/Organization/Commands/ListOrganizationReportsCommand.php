<?php

namespace App\Application\Organization\Commands;

final readonly class ListOrganizationReportsCommand
{
    public function __construct(
        public int $id,
        public int $page,
        public int $count,
    ) {}
}
