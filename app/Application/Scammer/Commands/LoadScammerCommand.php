<?php

namespace App\Application\Scammer\Commands;

use App\Models\Scammer;

final readonly class LoadScammerCommand
{
    public function __construct(
        public Scammer $scammer,
    ) {}
}
