<?php

namespace App\Application\Organization\Commands;

final readonly class OrganizationCalendarCommand
{
    public function __construct(
        public int $id,
        public int $year,
    ) {}
}
