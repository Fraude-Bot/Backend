<?php

namespace App\Application\Report\Commands;

final readonly class ShowReportCommand
{
    public function __construct(
        public int $id,
    ) {}
}
