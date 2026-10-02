<?php

namespace App\Application\Scammer\Commands;

final readonly class SuggestScammersCommand
{
    public function __construct(
        public string $query,
    ) {}
}
