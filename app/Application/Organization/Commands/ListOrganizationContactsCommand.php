<?php

namespace App\Application\Organization\Commands;

final readonly class ListOrganizationContactsCommand
{
    public function __construct(
        public int $id,
        public int $page,
        public int $count,
        public ?string $platform,
    ) {}
}
