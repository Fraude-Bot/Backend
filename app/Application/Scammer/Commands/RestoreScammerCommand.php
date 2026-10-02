<?php

namespace App\Application\Scammer\Commands;

final readonly class RestoreScammerCommand
{
    public function __construct(
        public int $id,
    ) {}
}
