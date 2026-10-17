<?php

namespace App\Application\Organization\Commands;

final readonly class ListPublicOrganizationScammersCommand
{
    public function __construct(
        public int $id,
        public int $page,
        public int $count,
    ) {}
}
