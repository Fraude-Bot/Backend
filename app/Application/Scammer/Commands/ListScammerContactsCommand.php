<?php

namespace App\Application\Scammer\Commands;

final readonly class ListScammerContactsCommand
{
    public function __construct(
        public int $id,
        public int $page,
        public int $count,
        public ?string $platform,
    ) {}
}
