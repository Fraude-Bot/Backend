<?php

namespace App\Application\Scammer\Commands;

final readonly class ShowScammerCommand
{
    public function __construct(
        public int $id,
    ) {}
}
