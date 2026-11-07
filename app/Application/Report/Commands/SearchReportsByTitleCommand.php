<?php

namespace App\Application\Report\Commands;

final readonly class SearchReportsByTitleCommand
{
    public function __construct(
        public string $query,
        public int $page,
        public int $count,
    ) {}
}
