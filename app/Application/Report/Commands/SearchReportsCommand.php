<?php

namespace App\Application\Report\Commands;

use App\Domain\Scammer\ValueObjects\Clue;

final readonly class SearchReportsCommand
{
    public function __construct(
        public Clue $clue,
        public int $page,
        public int $count,
    ) {}
}
